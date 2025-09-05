from __future__ import annotations
from .executor import run
from typing import List, Tuple


def run_cmd(cmd: List[str], cwd: str) -> Tuple[int, str, str]:
    return run(cmd, cwd=cwd, timeout=1800)

