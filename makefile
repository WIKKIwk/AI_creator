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

restart: compose-down compose-up
restart-prod: compose-down-prod compose-up-prod

compose-up:
	docker-compose up -d
compose-down:
	docker-compose down --remove-orphans

compose-up-prod:
	docker-compose -f docker-compose-prod.yml up -d
compose-down-prod:
	docker-compose -f docker-compose-prod.yml down --remove-orphans


############# APP COMMANDS #############
include .env
export $(shell sed 's/=.*//' .env)

args = $(filter-out $@,$(MAKECMDGOALS))

.PHONY: artisan
artisan:
	docker-compose exec php php artisan $(args)

.PHONY: composer
composer:
	docker-compose exec php composer $(args)

.PHONY: backup
backup:
	docker-compose exec db pg_dumpall -c -U postgres > backups/backup.sql
