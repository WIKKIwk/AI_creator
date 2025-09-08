from __future__ import annotations
from .executor import run
from typing import Optional


def git(cmd: list[str], cwd: str) -> tuple[int, str, str]:
    return run(["git", *cmd], cwd=cwd)


def current_sha(cwd: str) -> str:
    code, out, _ = git(["rev-parse", "HEAD"], cwd)
    return out.strip() if code == 0 else ""


def current_branch(cwd: str) -> str:
    code, out, _ = git(["rev-parse", "--abbrev-ref", "HEAD"], cwd)
    return out.strip() if code == 0 else ""


def create_branch(cwd: str, name: str) -> bool:
    code, _, _ = git(["checkout", "-b", name], cwd)
    return code == 0


def checkout(cwd: str, name: str) -> bool:
    code, _, _ = git(["checkout", name], cwd)
    return code == 0


def add_all(cwd: str) -> bool:
    code, _, _ = git(["add", "-A"], cwd)
    return code == 0


def commit(cwd: str, msg: str) -> bool:
    code, _, _ = git(["commit", "-m", msg], cwd)
    return code == 0


def push(cwd: str, remote: str, branch: str) -> bool:
    code, _, _ = git(["push", remote, branch], cwd)
    return code == 0


def tag(cwd: str, name: str) -> bool:
    code, _, _ = git(["tag", name], cwd)
    return code == 0


def hard_reset(cwd: str, sha: str) -> bool:
    code, _, _ = git(["reset", "--hard", sha], cwd)
    return code == 0


def revert_last(cwd: str) -> bool:
    code, _, _ = git(["revert", "--no-edit", "HEAD"], cwd)
    return code == 0


def has_changes(cwd: str) -> bool:
    code, out, _ = git(["status", "--porcelain"], cwd)
    if code != 0:
        return False
    return bool(out.strip())
