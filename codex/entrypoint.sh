#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT=${REPO_ROOT:-/app}

cd "$REPO_ROOT"

# Configure git identity if provided
if [[ -n "${GIT_AUTHOR_NAME:-}" ]]; then
  git config --global user.name "$GIT_AUTHOR_NAME"
fi
if [[ -n "${GIT_AUTHOR_EMAIL:-}" ]]; then
  git config --global user.email "$GIT_AUTHOR_EMAIL"
fi

# If GH_TOKEN present and origin is GitHub over HTTPS without token, rewrite to token URL
if [[ -n "${GH_TOKEN:-}" ]]; then
  ORIGIN_URL=$(git remote get-url origin 2>/dev/null || echo "")
  if [[ "$ORIGIN_URL" == https://github.com/* ]] || [[ "$ORIGIN_URL" == http://github.com/* ]]; then
    # Insert token into URL
    NEW_URL=$(echo "$ORIGIN_URL" | sed -E "s#https?://github.com/#https://\${GH_TOKEN}@github.com/#")
    git remote set-url origin "$NEW_URL" || true
  fi
fi

exec "$@"

