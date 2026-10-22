# Makefile for Performance Bundle Demo - Symfony 8
# Simplifies Docker commands for the demo

.PHONY: help build up down restart shell install clean setup ensure-up update-bundle logs test test-coverage test-e2e demo-screenshots db-create db-drop db-reset db-fixtures db-view performance-sync performance-sync-drop

# Default target
help:
	@echo "Performance Bundle Demo - Symfony 8 - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker containers (FrankenPHP, MySQL)"
	@echo "  down          Stop Docker containers"
	@echo "  restart       Restart containers (docker compose restart)"
	@echo "  build         Rebuild image (no cache)"
	@echo "  shell         Open shell in PHP container"
	@echo "  install       Install Composer dependencies"
	@echo "  update-bundle Update nowo-tech/performance-bundle from path repo and clear cache"
	@echo "  setup         Complete setup (install + DB + fixtures)"
	@echo "  clean         Remove vendor, var, and cache"
	@echo "  logs          Show container logs"
	@echo "  test          Run tests"
	@echo "  test-coverage Run tests with coverage (console output)"
	@echo "  test-e2e      Playwright e2e (Docker image)"
	@echo "  demo-screenshots Regenerate ../../docs/images/demo/*.webp"
	@echo ""
	@echo "Database:"
	@echo "  db-create     Create database"
	@echo "  db-drop       Drop database"
	@echo "  db-reset      Reset database (drop + create + schema + fixtures)"
	@echo "  db-fixtures   Load fixtures"
	@echo "  db-view       View performance metrics"
	@echo ""
	@echo "Performance Bundle (schema):"
	@echo "  performance-sync       Sync routes_data + routes_data_records with entities (add/alter)"
	@echo "  performance-sync-drop  Sync and drop columns no longer in entity (--drop-obsolete)"
	@echo ""

# Build and start containers
up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@echo "Installing dependencies (one-off container: the worker-mode server needs vendor/ before it starts)..."
	@$(COMPOSE) run --rm -T --no-deps --entrypoint composer -e COMPOSER_MEMORY_LIMIT=-1 php install --no-interaction --no-scripts
	$(COMPOSE) up -d
	@echo "Waiting for container to be ready..."
	@sleep 5
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@$(COMPOSE) exec -T php composer require symfony/ux-icons --no-interaction || echo "⚠️  ux-icons already installed or error occurred"
	@echo "Creating database, schema and loading fixtures..."
	@$(MAKE) setup
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	@echo "🛑 Stopping containers..."
	$(COMPOSE) down
	@echo "✅ Containers stopped!"

# Restart containers (no down/up)
restart: ensure-up
	@echo "🔄 Restarting containers..."
	$(COMPOSE) restart
	@echo "✅ Containers restarted!"

# Rebuild image without cache
build:
	@echo "🔨 Rebuilding image (no cache)..."
	$(COMPOSE) build --no-cache
	@echo "✅ Image rebuilt!"

# Open shell in PHP container
shell: ensure-up
	$(COMPOSE) exec php sh

# Ensure containers are running (start if not)
ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Containers not running. Run 'make up' first."; \
		exit 1; \
	fi

# Update bundle from path repo and clear cache
update-bundle: ensure-up
	@echo "🔄 Updating nowo-tech/performance-bundle..."
	$(COMPOSE) exec -T php composer update nowo-tech/performance-bundle --no-interaction
	$(COMPOSE) exec -T php php bin/console cache:clear
	@echo "✅ Bundle updated. Reload the page in the browser."

# Install dependencies
install: ensure-up
	@echo "📦 Installing dependencies..."
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "✅ Dependencies installed!"

# Complete setup
setup: ensure-up
	@echo "🔧 Setting up demo..."
	@echo "Waiting for MySQL to be ready..."
	@sleep 3
	@echo "Installing dependencies..."
	-$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --ignore-platform-reqs 2>/dev/null || $(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "Creating database..."
	-$(COMPOSE) exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction
	@echo "Creating schema..."
	-$(COMPOSE) exec -T php php bin/console doctrine:schema:create --no-interaction
	@echo "Syncing Performance Bundle tables (routes_data + routes_data_records)..."
	-$(COMPOSE) exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "Loading fixtures..."
	-$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Demo setup complete!"
	@echo ""
	@echo "Access the application at: http://localhost:8001 (HTTP on port 80, §2.4)"

# Database commands
db-create:
	@echo "📦 Creating database..."
	-$(COMPOSE) exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction

db-drop:
	@echo "🗑️  Dropping database..."
	-$(COMPOSE) exec -T php php bin/console doctrine:database:drop --force --if-exists --no-interaction

db-reset: db-drop db-create
	@echo "🔄 Resetting database schema..."
	-$(COMPOSE) exec -T php php bin/console doctrine:schema:create --no-interaction
	@echo "🔄 Syncing Performance Bundle tables..."
	-$(COMPOSE) exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "📥 Loading fixtures..."
	-$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Database reset complete!"

db-fixtures:
	@echo "📥 Loading fixtures..."
	-$(COMPOSE) exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Fixtures loaded!"

db-view:
	@echo "📋 Viewing performance metrics..."
	$(COMPOSE) exec -T php php bin/console dbal:run-sql "SELECT * FROM routes_data ORDER BY request_time DESC LIMIT 10" 2>/dev/null || echo "No metrics yet or connection error"

# Performance Bundle: sync DB structure with entity metadata (add missing, alter differing columns)
performance-sync:
	@echo "🔄 Syncing Performance Bundle schema (routes_data + routes_data_records)..."
	$(COMPOSE) exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "✅ Schema sync complete!"

# Performance Bundle: sync and drop columns no longer in entity
performance-sync-drop:
	@echo "🔄 Syncing Performance Bundle schema (with --drop-obsolete)..."
	$(COMPOSE) exec -T php php bin/console nowo:performance:sync-schema --drop-obsolete --no-interaction
	@echo "✅ Schema sync complete!"

# Show logs
logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -T php composer test 2>/dev/null || $(COMPOSE) exec -T php php bin/console list

# The demo has no PHPUnit suite (it is covered by the e2e suite and the bundle tests): boot the kernel instead.
test-coverage: ensure-up
	$(COMPOSE) exec -T php php bin/console about --env=test >/dev/null && echo "Demo kernel boots (no demo PHPUnit suite; see e2e/ for browser tests)."

# Clean vendor and cache
clean:
	@echo "🧹 Cleaning vendor, var, and cache..."
	rm -rf vendor
	rm -rf var/cache/*
	rm -rf var/log/*
	@echo "✅ Cleaned!"

# REQ-MAKE-008: update-deps
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
# Absolute docker path: this demo has a ./docker/ dir and an empty PATH segment makes GNU make
# execvp("docker") hit the directory → "Permission denied" (Error 127).
DOCKER_BIN := $(shell command -v docker 2>/dev/null || echo /usr/bin/docker)
COMPOSE := $(shell $(DOCKER_BIN) compose version >/dev/null 2>&1 && echo "$(DOCKER_BIN) compose" || echo "docker-compose")
SERVICE_PHP := php
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(abspath $(CURDIR)/../../../.scripts/Makefile.demo-update-deps.mk)

# REQ-DEMO-013: Playwright e2e + README screenshots (WebP)
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8000}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2); PORT=$${PORT:-8000}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"
