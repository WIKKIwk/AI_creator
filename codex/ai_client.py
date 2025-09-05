from __future__ import annotations
import requests
from typing import Dict, Any
from tenacity import retry, stop_after_attempt, wait_exponential


SYSTEM = (
    "Siz kod auditori va refaktor agentisiz. Sizdan iltimoslar: "
    "(1) mavjud test xatolarini tuzatish uchun minimal patch taklif qiling; "
    "(2) agar testlar o'tsa, xavfsiz optimallashtirishlar taklif qiling; "
    "(3) Faqat ruxsat etilgan yo'llarda o'zgartiring; (4) .env va maxfiy fayllarga tegmang; "
    "(5) Natija JSON formatida qayting: {title, summary, diffs: [{path, unified_diff}]}."
)


@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=1, max=8))
def propose(ai_url: str, prompt: str, context: Dict[str, Any]) -> Dict[str, Any]:
    # Use existing AI service /chat
    payload = {
        "user_id": 0,
        "org_id": None,
        "message": prompt,
        "history": [{"role": "system", "content": SYSTEM}],
        "context": context,
    }
    r = requests.post(ai_url.rstrip("/") + "/chat", json=payload, timeout=60)
    r.raise_for_status()
    data = r.json()
    # The service returns {answer: "..."}. Expect JSON string in answer.
    try:
        import json
        return json.loads(data.get("answer", "{}"))
    except Exception:
        return {"title": "no-change", "summary": "AI returned non-JSON", "diffs": []}

