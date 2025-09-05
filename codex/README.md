Codex – Self‑Improving Agent for PulBot
======================================

Codex is a lightweight Python agent that can propose safe code changes via the local AI service, run tests, deploy the app, check health, and auto‑rollback on failures.

Files
-----
- `codex/agent.py` – Typer CLI (main entry)
- `codex/config.py` – YAML config loader
- `codex/ai_client.py` – Talks to `AI_SERVICE_URL` (`/chat`)
- `codex/git_utils.py` – Git helpers
- `codex/executor.py` – Subprocess wrapper
- `codex/config.example.yml` – Configuration template
- `codex/requirements.txt` – Python deps

Quick Start (locally)
---------------------
1. Copy `codex/config.example.yml` to `codex/config.yml` and adjust settings.
2. Ensure `.env` contains `AI_SERVICE_URL` (e.g., `http://ai:8000`).
3. Create a branch and run a single cycle:
   `python -m codex.agent once`
4. Or run a daily loop:
   `python -m codex.agent run-loop --interval 86400`

In Docker (production)
----------------------
A `codex` service can run continuously. It mounts the repo and (optionally) Docker socket to run deploy commands.

Provide Git auth via env (HTTPS token or SSH), e.g. `GH_TOKEN` with a remote like `https://$GH_TOKEN@github.com/<owner>/<repo>.git`.

Safety
------
- Protected paths (e.g., `.env`) are never modified.
- Only allowed paths are touched by patches.
- Auto‑rollback using last known good SHA.

Extending
--------
- Add lint or static analysis commands in `config.yml`.
- Wire GitHub PR creation if `push_mode: pr` (future extension).
- Add custom health checks (HTTP endpoint or artisan commands).

