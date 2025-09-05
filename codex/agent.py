from __future__ import annotations
import os
import json
import time
import typer
from datetime import datetime
from rich import print as rprint
from pathlib import Path
from typing import Optional

from .config import CodexConfig
from .executor import run
from .git_utils import (
    current_sha, current_branch, create_branch, add_all, commit, push,
    tag, hard_reset, revert_last
)
from .ai_client import propose


app = typer.Typer(add_help_option=True, no_args_is_help=True)


def load_config(path: str | None) -> CodexConfig:
    cfg_path = path or os.environ.get("CODEX_CONFIG", "codex/config.yml")
    if not os.path.exists(cfg_path):
        # fallback to example if not present
        cfg_path = "codex/config.example.yml"
    return CodexConfig.load(cfg_path)


def save_last_good(repo: str, sha: str) -> None:
    Path(os.path.join(repo, "codex/.last_good_sha")).write_text(sha, encoding="utf-8")


def read_last_good(repo: str) -> str:
    p = Path(os.path.join(repo, "codex/.last_good_sha"))
    return p.read_text(encoding="utf-8").strip() if p.exists() else ""


def run_cmd(cmd: list[str], cwd: str) -> tuple[int, str, str]:
    return run(cmd, cwd=cwd, timeout=3600)


def test_suite(cfg: CodexConfig) -> bool:
    if not cfg.tests or not cfg.tests.get("command"):
        return True
    code, out, err = run_cmd(cfg.tests["command"], cfg.repo_root)
    rprint(out)
    if code != 0:
        rprint(f"[red]Tests failed[/red]\n{err}")
        return False
    return True


def deploy(cfg: CodexConfig) -> bool:
    if not cfg.deploy or not cfg.deploy.get("command"):
        return True
    code, out, err = run_cmd(cfg.deploy["command"], cfg.repo_root)
    rprint(out)
    if code != 0:
        rprint(f"[red]Deploy failed[/red]\n{err}")
        return False
    return True


def health_check(cfg: CodexConfig) -> bool:
    url = (cfg.health or {}).get("url")
    if not url:
        return True
    try:
        import requests
        retries = int((cfg.health or {}).get("retries", 10))
        timeout = int((cfg.health or {}).get("timeout", 5))
        for _ in range(retries):
            try:
                r = requests.get(url, timeout=timeout)
                if r.status_code < 500:
                    return True
            except Exception:
                pass
            time.sleep(2)
        return False
    except Exception:
        return True


def apply_diffs(cfg: CodexConfig, diffs: list[dict]) -> bool:
    # Prefer git apply for safety
    import fnmatch
    for d in diffs:
        diff_text = d.get("unified_diff", "").strip()
        if not diff_text:
            continue
        path = (d.get("path") or "").strip()
        # Protect critical files
        for pat in cfg.protected_paths or []:
            if fnmatch.fnmatch(path, pat):
                rprint(f"[yellow]Skipping protected path: {path}[/yellow]")
                return False
        # Enforce allow-list if provided
        if cfg.allow_paths:
            if not any(fnmatch.fnmatch(path, pat) for pat in cfg.allow_paths):
                rprint(f"[yellow]Skipping not-allowed path: {path}[/yellow]")
                return False
        tmp = Path(cfg.repo_root) / "codex" / ".tmp.patch"
        tmp.write_text(diff_text, encoding="utf-8")
        code, out, err = run_cmd(["bash","-lc", f"git apply --whitespace=fix {tmp}"], cfg.repo_root)
        if code != 0:
            rprint(f"[yellow]Could not apply a diff for {d.get('path')}[/yellow]\n{err}")
            return False
    return True


@app.command()
def once(config: Optional[str] = typer.Option(None, help="Path to config.yml")):
    cfg = load_config(config)
    repo = cfg.repo_root
    start_sha = current_sha(repo)
    rprint(f"Start SHA: [bold]{start_sha}[/bold]")

    rprint("[cyan]Running tests...[/cyan]")
    if not test_suite(cfg):
        # Ask AI to fix failing tests
        prompt = "Repo tests failed. Provide minimal safe patch as unified diff to fix failures."
        context = {"last_sha": start_sha}
        suggestion = propose(cfg.ai_url, prompt, context)
        if not apply_diffs(cfg, suggestion.get("diffs", [])):
            raise SystemExit(1)
        if not test_suite(cfg):
            raise SystemExit(1)

    # Optional lint step (placeholder)
    if cfg.lint and cfg.lint.get("command"):
        run_cmd(cfg.lint["command"], repo)

    # Commit & push
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    current = current_branch(repo)
    branch = current
    if cfg.push_mode != "direct":
        branch = f"{cfg.branch_prefix}/{ts}"
        create_branch(repo, branch)
    add_all(repo)
    title = "automated improvements"
    summary = "applied minimal safe changes"
    msg = cfg.commit_message_template.format(title=title, summary=summary)
    commit(repo, msg)
    push(repo, cfg.remote, branch)

    # Deploy
    rprint("[cyan]Deploying...[/cyan]")
    if not deploy(cfg):
        raise SystemExit(1)

    # Health check
    if not health_check(cfg):
        rprint("[red]Health check failed, rolling back...[/red]")
        last_good = read_last_good(repo) or start_sha
        if (cfg.rollback or {}).get("strategy", "git_reset") == "git_reset":
            hard_reset(repo, last_good)
        else:
            revert_last(repo)
        deploy(cfg)
        raise SystemExit(1)

    # Tag as last good
    good_sha = current_sha(repo)
    save_last_good(repo, good_sha)
    tag(repo, f"codex-good-{ts}")
    rprint(f"[green]Success. Last good: {good_sha}[/green]")


@app.command()
def run_loop(
    interval: int = typer.Option(86400, help="Seconds between runs; default daily"),
    config: Optional[str] = typer.Option(None, help="Path to config.yml")
):
    while True:
        try:
            once(config)
        except SystemExit as e:
            rprint(f"[red]Codex once failed with code {e.code}[/red]")
        except Exception as e:
            rprint(f"[red]Unexpected error: {e}[/red]")
        time.sleep(interval)


if __name__ == "__main__":
    app()
