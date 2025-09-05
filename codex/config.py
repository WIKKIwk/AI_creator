from __future__ import annotations
import os
import yaml
from dataclasses import dataclass, field
from typing import List, Dict, Any


@dataclass
class CodexConfig:
    repo_root: str
    docker_compose: str
    php_container: str
    ai_url: str
    branch_prefix: str
    remote: str
    push_mode: str
    health: Dict[str, Any] = field(default_factory=dict)
    tests: Dict[str, Any] = field(default_factory=dict)
    lint: Dict[str, Any] = field(default_factory=dict)
    deploy: Dict[str, Any] = field(default_factory=dict)
    restart_services: List[str] = field(default_factory=list)
    protected_paths: List[str] = field(default_factory=list)
    allow_paths: List[str] = field(default_factory=list)
    commit_message_template: str = "chore(codex): automated update"
    rollback: Dict[str, Any] = field(default_factory=dict)

    @staticmethod
    def load(path: str) -> "CodexConfig":
        with open(path, "r", encoding="utf-8") as f:
            data = yaml.safe_load(f) or {}
        # ai_url override from env
        ai_url = os.getenv("AI_SERVICE_URL", data.get("ai_url", "http://ai:8000"))
        data["ai_url"] = ai_url
        return CodexConfig(**data)

