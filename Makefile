SHELL := /bin/bash

DC := docker compose
SERVICE_APP := frankenphp
SERVICE_DB := mysql

.PHONY: help up down restart build ps logs app-shell db-shell composer-install composer-update composer-require sf migrate

help:
	@echo "Available targets:"
	@echo "  make up               - Start containers in background"
	@echo "  make down             - Stop and remove containers"
	@echo "  make restart          - Restart containers"
	@echo "  make build            - Build/rebuild images"
	@echo "  make ps               - Show running services"
	@echo "  make logs             - Follow logs"
	@echo "  make app-shell        - Open shell in frankenphp container"
	@echo "  make db-shell         - Open MySQL shell"
	@echo "  make composer-install - Run composer install"
	@echo "  make composer-update  - Run composer update"
	@echo "  make composer-require PACKAGE=vendor/package - Require package"
	@echo "  make sf CMD='about'   - Run Symfony console command"
	@echo "  make migrate          - Run doctrine migrations"

up:
	$(DC) up -d

down:
	$(DC) down

restart: down up

build:
	$(DC) up -d --build

ps:
	$(DC) ps

logs:
	$(DC) logs -f

app-shell:
	$(DC) exec $(SERVICE_APP) sh

db-shell:
	$(DC) exec $(SERVICE_DB) mysql -uapp -papp app

composer-install:
	$(DC) run --rm $(SERVICE_APP) composer install

composer-update:
	$(DC) run --rm $(SERVICE_APP) composer update

composer-require:
	@test -n "$(PACKAGE)" || (echo "Usage: make composer-require PACKAGE=vendor/package" && exit 1)
	$(DC) run --rm $(SERVICE_APP) composer require $(PACKAGE)

sf:
	@test -n "$(CMD)" || (echo "Usage: make sf CMD='about'" && exit 1)
	$(DC) exec $(SERVICE_APP) php bin/console $(CMD)

migrate:
	$(DC) exec $(SERVICE_APP) php bin/console doctrine:migrations:migrate --no-interaction
