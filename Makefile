SERVICES := gateway
s ?= gateway

.PHONY: setup up down ps logs sh test test-all pint

setup: ## First-time setup: env files, images, dependencies, app keys
	@test -f .env || cp .env.example .env
	docker compose build gateway
	@for svc in $(SERVICES); do \
		test -f services/$$svc/.env || cp services/$$svc/.env.example services/$$svc/.env; \
		docker compose run --rm --no-deps $$svc composer install; \
		docker compose run --rm --no-deps $$svc php artisan key:generate --ansi; \
	done

up: ## Start the whole stack
	docker compose up -d

down: ## Stop the stack
	docker compose down

ps:
	docker compose ps

logs: ## Tail logs: make logs s=gateway
	docker compose logs -f $(s)

sh: ## Shell into a service: make sh s=gateway
	docker compose exec $(s) bash

test: ## Run one service's tests: make test s=gateway
	docker compose run --rm --no-deps $(s) php artisan test

test-all:
	@for svc in $(SERVICES); do \
		docker compose run --rm --no-deps $$svc php artisan test || exit 1; \
	done

pint: ## Format one service: make pint s=gateway
	docker compose run --rm --no-deps $(s) vendor/bin/pint
