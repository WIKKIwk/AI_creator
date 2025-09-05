FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY codex/requirements.txt /app/codex/requirements.txt
RUN pip install --no-cache-dir -r codex/requirements.txt

# Install git and docker CLI to allow committing and restarting services
RUN apt-get update && \
    apt-get install -y --no-install-recommends git ca-certificates curl docker.io docker-compose-plugin && \
    rm -rf /var/lib/apt/lists/*

COPY codex /app/codex

COPY codex/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Mount repo at /app (compose will bind the project root)

ENTRYPOINT ["/entrypoint.sh"]
CMD ["python", "-m", "codex.agent", "run-loop", "--interval", "86400"]
