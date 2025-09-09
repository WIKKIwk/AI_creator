from __future__ import annotations
import os
import json
import time
import glob
import shutil
import typer
from datetime import datetime
from rich import print as rprint
from pathlib import Path
from typing import Optional

from .config import CodexConfig
from .executor import run
from .git_utils import (
    current_sha, current_branch, create_branch, add_all, commit, push,
    tag, hard_reset, revert_last, has_changes
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
        rprint("[green]Tests fixed by AI.[/green]")
    else:
        # Optionally propose safe improvements even when green
        if getattr(cfg, "improve_when_green", True):
            rprint("[cyan]Tests green. Proposing safe improvements...[/cyan]")
            prompt = (
                "Tests pass. Propose small, safe improvements (performance, readability, minor bugs) "
                "as minimal unified diffs. Do not change behavior."
            )
            context = {"last_sha": start_sha}
            suggestion = propose(cfg.ai_url, prompt, context)
            diffs = suggestion.get("diffs", [])
            if diffs:
                if not apply_diffs(cfg, diffs):
                    rprint("[yellow]No improvements applied.[/yellow]")
                else:
                    # Re-run tests after applying improvements
                    if not test_suite(cfg):
                        rprint("[red]Improvements broke tests, reverting...[/red]")
                        hard_reset(repo, start_sha)
                    else:
                        rprint("[green]Improvements validated by tests.[/green]")

    # Optional lint step (placeholder)
    if cfg.lint and cfg.lint.get("command"):
        code, out, err = run_cmd(cfg.lint["command"], repo)
        rprint(out)
        if code != 0:
            rprint("[red]Lint failed, reverting...[/red]")
            hard_reset(repo, start_sha)
            raise SystemExit(1)

    # Commit & push (only if there are changes)
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    current = current_branch(repo)
    branch = current
    if cfg.push_mode != "direct":
        branch = f"{cfg.branch_prefix}/{ts}"
        create_branch(repo, branch)
    if has_changes(repo):
        add_all(repo)
        # Try to read last suggestion if any, else default
        try:
            title = suggestion.get("title", "automated update") if 'suggestion' in locals() else "automated update"
            summary = suggestion.get("summary", "applied minimal safe changes") if 'suggestion' in locals() else "applied minimal safe changes"
        except Exception:
            title, summary = "automated update", "applied minimal safe changes"
        msg = cfg.commit_message_template.format(title=title, summary=summary)
        if not commit(repo, msg):
            rprint("[yellow]Nothing to commit or commit failed.[/yellow]")
        else:
            push(repo, cfg.remote, branch)
    else:
        rprint("[yellow]No changes detected; skipping commit/push.[/yellow]")

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


def _run_prompt(cfg: CodexConfig, prompt: str) -> None:
    repo = cfg.repo_root
    start_sha = current_sha(repo)
    rprint(f"Start SHA: [bold]{start_sha}[/bold]")

    context = {"last_sha": start_sha}
    rprint("[cyan]Proposing changes from custom prompt...[/cyan]")
    suggestion = propose(cfg.ai_url, prompt, context)
    diffs = suggestion.get("diffs", [])
    if not diffs:
        rprint("[yellow]No diffs from prompt; exiting.[/yellow]")
        return
    if not apply_diffs(cfg, diffs):
        raise SystemExit(1)
    # If tests fail, ask AI for minimal fixes up to 2 attempts
    attempts = 0
    while not test_suite(cfg) and attempts < 2:
        attempts += 1
        rprint(f"[yellow]Tests failing. Attempting AI fix #{attempts}...[/yellow]")
        fix = propose(cfg.ai_url,
                      "Tests failing after prompt changes. Provide minimal unified diff to fix failures only.",
                      {"last_sha": start_sha})
        if not apply_diffs(cfg, fix.get("diffs", [])):
            break
    if not test_suite(cfg):
        rprint("[red]Custom prompt changes still failing, reverting...[/red]")
        hard_reset(repo, start_sha)
        raise SystemExit(1)

    # Lint
    if cfg.lint and cfg.lint.get("command"):
        code, out, err = run_cmd(cfg.lint["command"], repo)
        rprint(out)
        if code != 0:
            rprint("[red]Lint failed, reverting...[/red]")
            hard_reset(repo, start_sha)
            raise SystemExit(1)

    # Commit & push
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    current = current_branch(repo)
    branch = current if cfg.push_mode == "direct" else f"{cfg.branch_prefix}/{ts}"
    if cfg.push_mode != "direct":
        create_branch(repo, branch)
    if has_changes(repo):
        add_all(repo)
        title = suggestion.get("title", "codex nudge")
        summary = suggestion.get("summary", prompt[:200])
        msg = cfg.commit_message_template.format(title=title, summary=summary)
        if commit(repo, msg):
            push(repo, cfg.remote, branch)
    # Deploy and health
    rprint("[cyan]Deploying...[/cyan]")
    if not deploy(cfg):
        raise SystemExit(1)
    if not health_check(cfg):
        rprint("[red]Health check failed, rolling back...[/red]")
        last_good = read_last_good(repo) or start_sha
        if (cfg.rollback or {}).get("strategy", "git_reset") == "git_reset":
            hard_reset(repo, last_good)
        else:
            revert_last(repo)
        deploy(cfg)
        raise SystemExit(1)
    good_sha = current_sha(repo)
    save_last_good(repo, good_sha)
    tag(repo, f"codex-nudge-{ts}")
    rprint(f"[green]Success. Last good: {good_sha}[/green]")


def _queue_dir(root: str) -> Path:
    d = Path(root) / "codex" / "queue"
    d.mkdir(parents=True, exist_ok=True)
    return d


def _processed_dir(root: str) -> Path:
    d = Path(root) / "codex" / "processed"
    d.mkdir(parents=True, exist_ok=True)
    return d


def process_queue(cfg: CodexConfig) -> None:
    qdir = _queue_dir(cfg.repo_root)
    pdir = _processed_dir(cfg.repo_root)
    files = sorted(glob.glob(str(qdir / "*.json")))
    if not files:
        return
    rprint(f"[cyan]Processing {len(files)} queued Codex job(s)...[/cyan]")
    for f in files:
        try:
            data = json.loads(Path(f).read_text(encoding="utf-8"))
            prompt = (data.get("prompt") or "").strip()
            if not prompt:
                rprint(f"[yellow]Skipping empty prompt in {os.path.basename(f)}[/yellow]")
            else:
                _run_prompt(cfg, prompt)
            # move to processed
            shutil.move(f, pdir / os.path.basename(f))
        except Exception as e:
            rprint(f"[red]Failed processing queue file {f}: {e}[/red]")


@app.command()
def run_loop(
    interval: int = typer.Option(86400, help="Seconds between maintenance runs; default daily"),
    queue_poll: int = typer.Option(60, help="Seconds between queue checks"),
    config: Optional[str] = typer.Option(None, help="Path to config.yml")
):
    last_maintenance = 0
    while True:
        try:
            cfg = load_config(config)
            # Process queued jobs frequently
            process_queue(cfg)
            now = time.time()
            if now - last_maintenance >= interval:
                once(config)
                last_maintenance = now
        except SystemExit as e:
            rprint(f"[red]Codex once failed with code {e.code}[/red]")
        except Exception as e:
            rprint(f"[red]Unexpected error: {e}[/red]")
        time.sleep(queue_poll)


@app.command()
def nudge(prompt: str = typer.Argument(..., help="Instruction for Codex to apply changes"),
          config: Optional[str] = typer.Option(None, help="Path to config.yml")):
    """Run a one-off Codex cycle using a custom prompt (e.g., from Telegram)."""
    cfg = load_config(config)
    _run_prompt(cfg, prompt)


def _proposals_dir(root: str) -> Path:
    d = Path(root) / "codex" / "proposals"
    d.mkdir(parents=True, exist_ok=True)
    return d


def propose_changes(cfg: CodexConfig, prompt: str) -> dict:
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    start_sha = current_sha(cfg.repo_root)
    suggestion = propose(cfg.ai_url, prompt, {"last_sha": start_sha})
    diffs = suggestion.get("diffs", []) or []
    files = []
    for d in diffs:
        p = (d.get("path") or "").strip()
        if p:
            files.append(p)
    pid = f"prop-{ts}"
    payload = {
        "id": pid,
        "created_at": ts,
        "base_sha": start_sha,
        "prompt": prompt,
        "suggestion": suggestion,
        "files": files,
    }
    (_proposals_dir(cfg.repo_root) / f"{pid}.json").write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    return {
        "id": pid,
        "title": suggestion.get("title", "proposal"),
        "summary": suggestion.get("summary", ""),
        "files": files,
    }


def apply_proposal(cfg: CodexConfig, proposal_id: str) -> None:
    repo = cfg.repo_root
    pfile = _proposals_dir(repo) / f"{proposal_id}.json"
    if not pfile.exists():
        raise SystemExit(f"proposal not found: {proposal_id}")
    data = json.loads(pfile.read_text(encoding="utf-8"))
    start_sha = data.get("base_sha") or current_sha(repo)
    suggestion = data.get("suggestion") or {}
    diffs = suggestion.get("diffs", []) or []
    if not diffs:
        rprint("[yellow]Proposal contains no diffs; aborting[/yellow]")
        return
    if not apply_diffs(cfg, diffs):
        raise SystemExit(1)
    attempts = 0
    while not test_suite(cfg) and attempts < 2:
        attempts += 1
        rprint(f"[yellow]Tests failing. Attempting AI fix #{attempts}...[/yellow]")
        fix = propose(cfg.ai_url,
                      "Tests failing after proposal apply. Provide minimal unified diff to fix failures only.",
                      {"last_sha": start_sha})
        if not apply_diffs(cfg, fix.get("diffs", [])):
            break
    if not test_suite(cfg):
        rprint("[red]Changes still failing, reverting...[/red]")
        hard_reset(repo, start_sha)
        raise SystemExit(1)

    # Strict lint
    if cfg.lint and cfg.lint.get("command"):
        code, out, err = run_cmd(cfg.lint["command"], repo)
        rprint(out)
        if code != 0:
            rprint("[red]Lint failed, reverting...[/red]")
            hard_reset(repo, start_sha)
            raise SystemExit(1)

    # Commit & push
    ts = datetime.utcnow().strftime("%Y%m%d-%H%M%S")
    current = current_branch(repo)
    branch = current if cfg.push_mode == "direct" else f"{cfg.branch_prefix}/{ts}"
    if cfg.push_mode != "direct":
        create_branch(repo, branch)
    if has_changes(repo):
        add_all(repo)
        title = suggestion.get("title", "codex proposal")
        summary = suggestion.get("summary", "applied approved changes")
        msg = cfg.commit_message_template.format(title=title, summary=summary)
        if commit(repo, msg):
            push(repo, cfg.remote, branch)

    # Deploy & health
    rprint("[cyan]Deploying...[/cyan]")
    if not deploy(cfg):
        raise SystemExit(1)
    if not health_check(cfg):
        rprint("[red]Health check failed, rolling back...[/red]")
        last_good = read_last_good(repo) or start_sha
        if (cfg.rollback or {}).get("strategy", "git_reset") == "git_reset":
            hard_reset(repo, last_good)
        else:
            revert_last(repo)
        deploy(cfg)
        raise SystemExit(1)
    good_sha = current_sha(repo)
    save_last_good(repo, good_sha)
    tag(repo, f"codex-prop-{ts}")
    rprint(f"[green]Success. Last good: {good_sha}[/green]")


@app.command()
def propose_cmd(prompt: str = typer.Argument(..., help="Ask Codex to propose changes only"),
                config: Optional[str] = typer.Option(None, help="Path to config.yml")):
    cfg = load_config(config)
    meta = propose_changes(cfg, prompt)
    rprint(json.dumps(meta, ensure_ascii=False))


@app.command()
def apply(proposal_id: str = typer.Argument(..., help="Proposal id to apply"),
          config: Optional[str] = typer.Option(None, help="Path to config.yml")):
    cfg = load_config(config)
    apply_proposal(cfg, proposal_id)


if __name__ == "__main__":
    app()
