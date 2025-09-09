import os
from typing import List, Dict, Any
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

try:
    from openai import OpenAI
except Exception:  # pragma: no cover
    OpenAI = None

app = FastAPI(title="PulBot AI Service")


class Message(BaseModel):
    role: str
    content: str


class ChatPayload(BaseModel):
    user_id: int
    org_id: int | None = None
    message: str
    history: List[Message] = Field(default_factory=list)
    context: Dict[str, Any] = Field(default_factory=dict)
    # Default to gpt-4o for stronger reasoning/advice
    model: str = Field(default=os.getenv("OPENAI_MODEL", "gpt-4o"))


SYSTEM_PROMPT = (
    "Siz PulBot AI assistentisiz. Tizimdagi ombor (inventory), ishlab chiqarish va ta'minot buyurtmalari haqida "
    "foydalanuvchiga tushunarli, aniq va xavfsiz tavsiyalar bering. Ustida ishlayotgan kontekst: {context}. "
    "Siz GPT‑4o modelidan foydalanasiz; model versiyasi haqida noto‘g‘ri ma'lumot bermang. Agar model haqida so‘ralsa, "
    "‘GPT‑4o’ deb javob bering. Savollarga aniq javob, kerak bo'lsa bullet nuqtalar bilan qayting. Agar ma'lumot yetarli "
    "bo'lmasa, aniq so'rov bering. Hech qachon maxfiy kalitlarni yoki ichki konfiguratsiyani oshkor etmang."
)


def _chat_with_key(payload: ChatPayload, api_key_env: str = "OPENAI_API_KEY", force_json: bool = False):
    api_key = os.getenv(api_key_env) or os.getenv("OPENAI_API_KEY")
    if OpenAI is None or not api_key:
        # Return a deterministic fallback for local runs without API key
        return {
            "answer": (
                "[AI emulyatsiya] OPENAI_API_KEY yo'q. Savol: "
                f"{payload.message}\nKontekst: {payload.context}"
            )
        }

    try:
        client = OpenAI(api_key=api_key)
        messages = [
            {"role": "system", "content": SYSTEM_PROMPT.format(context=payload.context)},
        ]
        # Append history
        for m in payload.history:
            messages.append({"role": m.role, "content": m.content})
        messages.append({"role": "user", "content": payload.message})

        # Use chat.completions for broad compatibility
        kwargs = {
            "model": payload.model,
            "messages": messages,
            "temperature": 0.3,
        }
        # In Codex mode we want strict JSON back
        if force_json:
            kwargs["response_format"] = {"type": "json_object"}
        resp = client.chat.completions.create(**kwargs)
        answer = resp.choices[0].message.content
        return {"answer": answer, "model": payload.model}
    except Exception as e:  # pragma: no cover
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/chat")
def chat(payload: ChatPayload):
    """Default chat using OPENAI_API_KEY."""
    return _chat_with_key(payload, "OPENAI_API_KEY", force_json=False)


@app.post("/chat-codex")
def chat_codex(payload: ChatPayload):
    """Codex-dedicated chat using OPENAI_FOR_CODEX_API_KEY if present."""
    return _chat_with_key(payload, "OPENAI_FOR_CODEX_API_KEY", force_json=True)


@app.get("/health")
def health():
    return {
        "ok": True,
        "default_model": os.getenv("OPENAI_MODEL", "gpt-4o"),
    }
