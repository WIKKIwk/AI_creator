.DEFAULT_GOAL := full

############# BUILD PUSH CONTAINERS #############
build: php-build nginx-build
push: php-push nginx-push

php-build:
	docker --log-level debug build --file _docker/production/php/Dockerfile --tag ravshan014/tour-admin-php:1 .

nginx-build:
	docker --log-level debug build --file _docker/production/nginx/Dockerfile --tag ravshan014/tour-admin-nginx:1 .

php-push:
	docker push ravshan014/tour-admin-php:1

nginx-push:
	docker push ravshan014/tour-admin-nginx:1

push:
	make php-push
	make nginx-push


############# DOCKER COMPOSE #############

# Wrapper for docker compose (supports v1 and v2)
DC=bash ./bin/dc
DC_PROD=$(DC) -f docker-compose-prod.yml

restart: compose-down compose-up
restart-prod: compose-down-prod compose-up-prod

compose-up:
	$(DC) up -d
compose-down:
	$(DC) down --remove-orphans

compose-up-prod:
	$(DC) -f docker-compose-prod.yml up -d
compose-down-prod:
	$(DC) -f docker-compose-prod.yml down --remove-orphans

.PHONY: compose-build-prod
compose-build-prod:
	$(DC) -f docker-compose-prod.yml build --pull=false


############# APP COMMANDS #############
# Load .env variables if the file exists (first run may not have it)
-include .env
ifneq (,$(wildcard .env))
export $(shell sed 's/=.*//' .env)
endif

args = $(filter-out $@,$(MAKECMDGOALS))

.PHONY: artisan
artisan:
	$(DC) exec php php artisan $(args)

.PHONY: composer
composer:
	$(DC) exec php composer $(args)

.PHONY: backup
backup:
	$(DC) exec db pg_dumpall -c -U postgres > backups/backup.sql


############# ONE-COMMAND START #############
.PHONY: start
start:
	bash ./scripts/start.sh

.PHONY: bot
bot:
	$(DC) exec php php artisan bot:run

.PHONY: ai-key
ai-key:
	@if [ -z "$(KEY)" ]; then echo "Usage: make ai-key KEY=sk-..."; exit 1; fi
	bash ./scripts/set_openai_key.sh "$(KEY)"
	@echo "Restarting AI service to pick up env..."
	$(DC) up -d ai


############# CODEX (Self-Improving Agent) #############
.PHONY: codex-once codex-loop codex-logs codex-stop codex-restart codex-env prod

# Run Codex once in an ephemeral container (uses prod compose)
codex-once:
	$(DC_PROD) run --rm codex python -m codex.agent once

# Start Codex in loop mode (detached, daily by default)
codex-loop:
	$(DC_PROD) up -d codex

# Tail Codex logs
codex-logs:
	$(DC_PROD) logs -f codex

# Stop Codex container
codex-stop:
	$(DC_PROD) stop codex || true

# Restart Codex container
codex-restart:
	$(DC_PROD) up -d codex --force-recreate

# Quick env sanity for Codex
codex-env:
	@echo "AI_SERVICE_URL = $(AI_SERVICE_URL)" ; \
	 echo "GH_TOKEN       = $${GH_TOKEN:+**** set ****}" ; \
	 echo "GIT_AUTHOR     = $(GIT_AUTHOR_NAME) <$(GIT_AUTHOR_EMAIL)>" ; \
	 echo "CODEX_CONFIG   = $${CODEX_CONFIG:-codex/config.yml}" ; \
	 test -n "$(AI_SERVICE_URL)" || { echo "AI_SERVICE_URL is required" >&2; exit 1; } ; \
	 test -n "$(GIT_AUTHOR_NAME)" || { echo "GIT_AUTHOR_NAME is recommended" >&2; } ; \
	 test -n "$(GIT_AUTHOR_EMAIL)" || { echo "GIT_AUTHOR_EMAIL is recommended" >&2; } ; \
	 echo "OK"

# Bring up full prod stack including Codex
prod: compose-up-prod


############# ONE-COMMAND FULL START (PROD) #############
.PHONY: full env-prepare-prod init-prod tests-prod

# Prepare .env (copy example if missing)
env-prepare-prod:
	@if [ ! -f .env ]; then cp .env.example .env; fi
	@echo "Using AI_SERVICE_URL=$(AI_SERVICE_URL)"
	@echo "OPENAI_API_KEY=$${OPENAI_API_KEY:+**** set ****}"
	@echo "GH_TOKEN=$${GH_TOKEN:+**** set ****}"
	@echo "GIT_AUTHOR=$(GIT_AUTHOR_NAME) <$(GIT_AUTHOR_EMAIL)>"
	@true

# Initialize app inside prod containers
init-prod:
	@echo "[init] Waiting for Postgres to be ready..." ; \
	ATTEMPTS=0; \
	until $(DC_PROD) exec -T db pg_isready -U "${DB_USERNAME}" -d "${DB_DATABASE}" >/dev/null 2>&1; do \
	  ATTEMPTS=$$((ATTEMPTS+1)); \
	  if [ $$ATTEMPTS -ge 60 ]; then echo "Postgres is not ready after 60s" >&2; exit 1; fi; \
	  sleep 1; \
	done
	@echo "[init] Installing composer dependencies..." ; \
	$(DC_PROD) exec -T php composer install --no-interaction --prefer-dist --no-ansi --no-progress
	@echo "[init] Generating app key (idempotent, if .env exists)..." ; \
	$(DC_PROD) exec -T php sh -lc 'if [ -f /app/.env ]; then php artisan key:generate --force; else echo "[init] Skipping key:generate (no .env file in container)"; fi'
	@echo "[init] Running migrations..." ; \
	$(DC_PROD) exec -T php php artisan migrate --force
	@echo "[init] Seeding database..." ; \
	$(DC_PROD) exec -T php php artisan db:seed --force || true

# Optional test run (set RUN_TESTS=1)
RUN_TESTS ?= 0
tests-prod:
	@if [ "$(RUN_TESTS)" = "1" ]; then \
	  echo "[tests] Running feature tests..."; \
	  $(DC_PROD) exec -T php php artisan test --env=testing; \
	else \
	  echo "[tests] Skipped. Set RUN_TESTS=1 to enable."; \
	fi

# Full flow: prepare env -> up stack -> init -> ensure Codex running
full: env-prepare-prod compose-build-prod compose-up-prod init-prod codex-loop
	@echo "All services are up. View logs: make codex-logs"
