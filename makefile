.DEFAULT_GOAL := start

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
