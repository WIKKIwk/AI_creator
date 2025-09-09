from __future__ import annotations
import os
import json
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Optional

from .agent import _run_prompt, load_config, propose_changes, apply_proposal


def make_handler(config_path: Optional[str]):
    secret = os.environ.get("CODEX_SECRET", "")

    class NudgeHandler(BaseHTTPRequestHandler):
        def _send(self, code: int, body: dict):
            data = json.dumps(body).encode("utf-8")
            self.send_response(code)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)

        def log_message(self, fmt, *args):
            return

        def do_POST(self):  # noqa: N802
            if self.path not in ("/nudge", "/propose", "/apply"):
                return self._send(404, {"error": "not found"})
            if secret:
                token = self.headers.get("X-Codex-Token", "")
                if token != secret:
                    return self._send(403, {"error": "forbidden"})
            length = int(self.headers.get("Content-Length", "0") or 0)
            try:
                payload = json.loads(self.rfile.read(length).decode("utf-8")) if length else {}
            except Exception:
                return self._send(400, {"error": "invalid json"})
            cfg = load_config(config_path)
            if self.path == "/nudge":
                prompt = (payload.get("prompt") or "").strip()
                if not prompt:
                    return self._send(400, {"error": "prompt required"})
                threading.Thread(target=_run_prompt, args=(cfg, prompt), daemon=True).start()
                return self._send(202, {"status": "accepted"})
            if self.path == "/propose":
                prompt = (payload.get("prompt") or "").strip()
                if not prompt:
                    return self._send(400, {"error": "prompt required"})
                meta = propose_changes(cfg, prompt)
                return self._send(200, meta)
            if self.path == "/apply":
                pid = (payload.get("id") or "").strip()
                if not pid:
                    return self._send(400, {"error": "id required"})
                threading.Thread(target=apply_proposal, args=(cfg, pid), daemon=True).start()
                return self._send(202, {"status": "accepted", "id": pid})

    return NudgeHandler


def start_http_server(config_path: Optional[str], host: str = "0.0.0.0", port: int = None):
    port = port or int(os.environ.get("CODEX_PORT", "8090"))
    server = ThreadingHTTPServer((host, port), make_handler(config_path))
    t = threading.Thread(target=server.serve_forever, daemon=True)
    t.start()
    return server
