SERVICES := gateway auth catalog orders
DB_SERVICES := auth catalog orders
s ?= gateway

.PHONY: setup keys migrate seed up restart-workers down ps logs sh test test-shared test-all e2e pint lint

setup: ## First-time setup: env files, images, dependencies, app keys, JWT keys, databases
	@test -f .env || cp .env.example .env
	docker compose build gateway
	@for svc in $(SERVICES); do \
		test -f services/$$svc/.env || cp services/$$svc/.env.example services/$$svc/.env; \
		docker compose run --rm --no-deps $$svc composer install || exit 1; \
		docker compose run --rm --no-deps $$svc php artisan key:generate --ansi || exit 1; \
	done
	$(MAKE) keys migrate

keys: ## Generate the JWT key pair in infra/keys (kept if it already exists)
	docker compose run --rm --no-deps auth php artisan jwt:generate-keys

migrate: ## Run migrations for every service that owns a database
	@for svc in $(DB_SERVICES); do \
		docker compose run --rm $$svc php artisan migrate --force || exit 1; \
	done

seed: ## Seed local development data (admin@example.com / password, sample catalog)
	@for svc in $(DB_SERVICES); do \
		docker compose run --rm $$svc php artisan db:seed --force || exit 1; \
	done

up: ## Start the whole stack
	docker compose up -d

restart-workers: ## Reload code in the long-running outbox and consumer workers
	docker compose restart catalog-outbox catalog-consumer orders-outbox orders-consumer

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

test-shared: ## Run the shared package's own tests
	docker compose run --rm --no-deps -w /workspace/packages/shared gateway sh -c 'composer install -q && vendor/bin/phpunit'

test-all: test-shared
	@for svc in $(SERVICES); do \
		docker compose run --rm --no-deps $$svc php artisan test || exit 1; \
	done

e2e: ## End-to-end checkout test against the running stack (needs: make seed, make up)
	docker compose run --rm --no-deps gateway php /workspace/tests/e2e/checkout.php

pint: ## Format one service: make pint s=gateway
	docker compose run --rm --no-deps $(s) vendor/bin/pint

lint: ## Check code style in every service without changing files
	@for svc in $(SERVICES); do \
		docker compose run --rm --no-deps $$svc vendor/bin/pint --test || exit 1; \
	done
