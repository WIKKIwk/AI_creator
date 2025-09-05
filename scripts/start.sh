#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# Use wrapper via bash to avoid execute-bit requirement
DC="bash ./bin/dc"

need() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing dependency: $1" >&2
    return 1
  fi
}

echo "[PulBot] Checking dependencies..."
need docker

if ! $DC version >/dev/null 2>&1; then
  echo "Docker Compose not available via wrapper ($DC)." >&2
  exit 1
fi

echo "[PulBot] Preparing .env ..."
if [ ! -f .env ]; then
  cp .env.example .env
fi

# Load current env to use defaults and for compose
set +u
set -a
source ./.env
set +a
set -u

# Portable in-place sed (GNU/BSD)
sed_inplace() {
  if sed --version >/dev/null 2>&1; then
    sed -i "$@"
  else
    sed -i '' "$@"
  fi
}

# Set sensible defaults if empty
update_env_var() {
  local key="$1"; shift
  local val="$1"; shift || true
  if ! grep -qE "^${key}=" .env; then
    echo "${key}=${val}" >> .env
  else
    # Only replace if currently empty (i.e., KEY= or KEY="")
    if grep -qE "^${key}=$|^${key}=\"\"$" .env; then
      # Use sed to replace the line (portable)
      sed_inplace "s|^${key}=.*|${key}=${val}|" .env
    fi
  fi
}

# Compute UID/GID defaults
CUR_UID=$(id -u)
CUR_GID=$(id -g)
CUR_USER=${USER:-developer}

update_env_var APP_PORT "${APP_PORT:-8080}"
update_env_var PGADMIN_PORT "${PGADMIN_PORT:-5050}"

update_env_var DB_HOST "${DB_HOST:-db}"
update_env_var DB_PORT "${DB_PORT:-5432}"
update_env_var DB_DATABASE "${DB_DATABASE:-pulbot}"
update_env_var DB_USERNAME "${DB_USERNAME:-postgres}"
update_env_var DB_PASSWORD "${DB_PASSWORD:-postgres}"

update_env_var REDIS_HOST "${REDIS_HOST:-redis}"
update_env_var REDIS_PORT "${REDIS_PORT:-6379}"

# AI defaults
update_env_var AI_SERVICE_URL "${AI_SERVICE_URL:-http://ai:8000}"
# Default model upgraded to gpt-4o for better answers
update_env_var OPENAI_MODEL "${OPENAI_MODEL:-gpt-4o}"
update_env_var OPENAI_API_KEY "${OPENAI_API_KEY:-}"

update_env_var USER "${USER:-$CUR_USER}"
update_env_var UID "${UID:-$CUR_UID}"
update_env_var GID "${GID:-$CUR_GID}"

if ! grep -qE '^APP_URL=' .env; then
  echo "APP_URL=http://localhost:${APP_PORT:-8080}" >> .env
fi

# Reload after updates
set +u
set -a
source ./.env
set +a
set -u

# If Redis host is still 127.0.0.1 (from old .env), replace with docker service name
if grep -qE '^REDIS_HOST=127\.0\.0\.1$' .env; then
  echo "[PulBot] Adjusting REDIS_HOST to 'redis' for Docker network"
  sed_inplace 's/^REDIS_HOST=127\.0\.0\.1$/REDIS_HOST=redis/' .env
  # reload again after adjustment
  set +u
  set -a
  source ./.env
  set +a
  set -u
fi

# Ensure required secrets are set
prompt_if_empty() {
  local key="$1"; local prompt_msg="$2"; local out_var="$3"
  local cur_val
  cur_val=$(grep -E "^${key}=" .env | sed -E "s/^${key}=//") || true
  if [ -z "$cur_val" ] || [ "$cur_val" = '""' ]; then
    read -rp "$prompt_msg: " input
    if [ -z "$input" ]; then
      echo "Value required for $key" >&2; exit 1
    fi
    # escape slashes for sed safety
    local escaped=${input//\//\\/}
    if grep -qE "^${key}=" .env; then
      sed_inplace "s|^${key}=.*|${key}=${escaped}|" .env
    else
      echo "${key}=${escaped}" >> .env
    fi
    printf -v "$out_var" '%s' "$input"
  else
    printf -v "$out_var" '%s' "$cur_val"
  fi
}

TELEGRAM_BOT_TOKEN_VAL=""
ADMIN_EMAIL_VAL=""
ADMIN_PASSWORD_VAL=""
OPENAI_API_KEY_VAL=""

prompt_if_empty TELEGRAM_BOT_TOKEN "Enter TELEGRAM_BOT_TOKEN" TELEGRAM_BOT_TOKEN_VAL
prompt_if_empty ADMIN_EMAIL "Enter ADMIN_EMAIL" ADMIN_EMAIL_VAL
prompt_if_empty ADMIN_PASSWORD "Enter ADMIN_PASSWORD" ADMIN_PASSWORD_VAL
# OpenAI is optional; skip prompt if empty but keep placeholder
if [ -z "$(grep -E '^OPENAI_API_KEY=' .env | sed -E 's/^OPENAI_API_KEY=//')" ]; then
  read -rp "(Optional) Enter OPENAI_API_KEY (or leave blank): " input
  if [ -n "$input" ]; then
    sed_inplace "s|^OPENAI_API_KEY=.*|OPENAI_API_KEY=${input}|" .env || echo "OPENAI_API_KEY=${input}" >> .env
  fi
fi

echo "[PulBot] Bringing containers up..."
$DC up -d

echo "[PulBot] Waiting for Postgres to be ready..."
ATTEMPTS=0
until $DC exec -T db pg_isready -U "${DB_USERNAME}" -d "${DB_DATABASE}" >/dev/null 2>&1; do
  ATTEMPTS=$((ATTEMPTS+1))
  if [ $ATTEMPTS -ge 60 ]; then
    echo "Postgres is not ready after 60s, aborting." >&2
    exit 1
  fi
  sleep 1
done

echo "[PulBot] Installing composer deps..."
$DC exec -T php composer install --no-interaction --prefer-dist

echo "[PulBot] Generating app key..."
$DC exec -T php php artisan key:generate --force

echo "[PulBot] Running migrations..."
$DC exec -T php php artisan migrate --force

echo "[PulBot] Seeding database (admin user)..."
$DC exec -T php php artisan db:seed --force

echo "[PulBot] (Optional) Generating auth codes for users..."
$DC exec -T php php artisan app:regenerate-auth-codes || true

echo "[PulBot] All set. Starting bot (Ctrl+C to stop)."
exec $DC exec php php artisan bot:run
