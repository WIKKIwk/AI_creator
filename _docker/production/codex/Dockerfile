FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY codex/requirements.txt /app/codex/requirements.txt
# Install agent deps (no compose here to avoid PyYAML conflicts)
RUN pip install --no-cache-dir -r codex/requirements.txt

# Install docker CLI + compose plugin from Docker's official APT repo and git
RUN apt-get update && \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg bash && \
    install -m 0755 -d /etc/apt/keyrings && \
    curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg && \
    chmod a+r /etc/apt/keyrings/docker.gpg && \
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian $(. /etc/os-release; echo $VERSION_CODENAME) stable" > /etc/apt/sources.list.d/docker.list && \
    apt-get update && \
    apt-get install -y --no-install-recommends docker-ce-cli docker-compose-plugin git && \
    rm -rf /var/lib/apt/lists/*

COPY codex /app/codex

COPY codex/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Mount repo at /app (compose will bind the project root)

ENTRYPOINT ["/entrypoint.sh"]
CMD ["python", "-m", "codex.agent", "run-loop", "--interval", "86400"]
