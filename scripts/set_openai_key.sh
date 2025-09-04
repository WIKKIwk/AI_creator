#!/usr/bin/env bash
set -euo pipefail

KEY_VALUE=${1:-}
if [ -z "$KEY_VALUE" ]; then
  echo "Usage: $0 <OPENAI_API_KEY>" >&2
  exit 1
fi

if [ ! -f .env ]; then
  echo ".env not found; creating from example" >&2
  cp .env.example .env
fi

# Portable sed -i
if sed --version >/dev/null 2>&1; then
  SED_INPLACE=(sed -i)
else
  SED_INPLACE=(sed -i '')
fi

if grep -qE '^OPENAI_API_KEY=' .env; then
  "${SED_INPLACE[@]}" "s|^OPENAI_API_KEY=.*|OPENAI_API_KEY=${KEY_VALUE}|" .env
else
  echo "OPENAI_API_KEY=${KEY_VALUE}" >> .env
fi

echo "OPENAI_API_KEY saved to .env"

