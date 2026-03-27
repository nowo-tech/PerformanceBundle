# Makefile for Performance Bundle Demo - Symfony 7
# Simplifies Docker commands for the demo

.PHONY: help build up down shell install clean setup ensure-up update-bundle

# Default target
help:
	@echo "Performance Bundle Demo - Symfony 7 - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  build         Build Docker images (without starting)"
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
	@echo "  update-bundle Update bundle from path repo and clear cache"
	@echo "  test          Run tests"
	@echo "  test-coverage Run tests with coverage (console output)"
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

# Build Docker images (without starting)
build:
	@echo "🔨 Building demo images..."
	docker-compose build
	@echo "✅ Build complete!"

# Build and start containers
up:
	@echo "🚀 Starting demo containers..."
	@if [ ! -f .env ]; then \
		echo "⚠️  .env file not found. Copying .env.example..."; \
		cp .env.example .env; \
		echo "✅ .env file created from .env.example"; \
	fi
	docker-compose up -d --build
	@echo "⏳ Waiting for MySQL to be ready..."
	@sleep 8
	@echo "✅ Containers started!"
	@echo "📦 Installing dependencies..."
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "📦 Installing symfony/ux-icons..."
	docker-compose exec -T php composer require symfony/ux-icons --no-interaction || echo "⚠️  ux-icons already installed or error occurred"
	@echo "✅ Dependencies installed!"
	@echo "Creating database, schema and loading fixtures..."
	@$(MAKE) setup
	@DEFAULT_URI=$$(grep -E '^DEFAULT_URI=' .env | cut -d= -f2-) ; \
		echo "Demo started at: $${DEFAULT_URI:-http://localhost:8000}"

# Stop containers
down:
	@echo "🛑 Stopping containers..."
	docker-compose down
	@echo "✅ Containers stopped!"

# Restart containers (no down/up)
restart: ensure-up
	@echo "🔄 Restarting containers..."
	docker-compose restart
	@echo "✅ Containers restarted!"

# Rebuild image without cache
build:
	@echo "🔨 Rebuilding image (no cache)..."
	docker-compose build --no-cache
	@echo "✅ Image rebuilt!"

# Ensure container is running (start if not). Used by install, shell, setup, update-bundle, etc.
ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 5; \
	fi

# Open shell in PHP container
shell: ensure-up
	docker-compose exec php sh

# Ensure containers are running (start if not)
ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Containers not running. Run 'make up' first."; \
		exit 1; \
	fi

# Update bundle from path repo and clear cache
update-bundle: ensure-up
	@echo "🔄 Updating nowo-tech/performance-bundle..."
	docker-compose exec -T php composer update nowo-tech/performance-bundle --no-interaction
	docker-compose exec -T php php bin/console cache:clear
	@echo "✅ Bundle updated. Reload the page in the browser."

# Install dependencies
install: ensure-up
	@echo "📦 Installing dependencies..."
	docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "✅ Dependencies installed!"

# Complete setup
setup: ensure-up
	@echo "🔧 Setting up demo..."
	@echo "Waiting for MySQL to be ready..."
	@sleep 3
	@echo "Installing dependencies..."
	-docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction --ignore-platform-reqs 2>/dev/null || docker-compose exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction
	@echo "Creating database..."
	-docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction
	@echo "Creating schema..."
	-docker-compose exec -T php php bin/console doctrine:schema:create --no-interaction
	@echo "Syncing Performance Bundle tables (routes_data + routes_data_records)..."
	-docker-compose exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "Loading fixtures..."
	-docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Demo setup complete!"
	@echo ""
	@echo "Access the application at: http://localhost:8000 (HTTP on port 80, §2.4)"

# Database commands
db-create:
	@echo "📦 Creating database..."
	-docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction

db-drop:
	@echo "🗑️  Dropping database..."
	-docker-compose exec -T php php bin/console doctrine:database:drop --force --if-exists --no-interaction

db-reset: db-drop db-create
	@echo "🔄 Resetting database schema..."
	-docker-compose exec -T php php bin/console doctrine:schema:create --no-interaction
	@echo "🔄 Syncing Performance Bundle tables..."
	-docker-compose exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "📥 Loading fixtures..."
	-docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Database reset complete!"

db-fixtures:
	@echo "📥 Loading fixtures..."
	-docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Fixtures loaded!"

db-view:
	@echo "📋 Viewing performance metrics..."
	docker-compose exec -T php php bin/console dbal:run-sql "SELECT * FROM routes_data ORDER BY request_time DESC LIMIT 10" 2>/dev/null || echo "No metrics yet or connection error"

# Performance Bundle: sync DB structure with entity metadata (add missing, alter differing columns)
performance-sync:
	@echo "🔄 Syncing Performance Bundle schema (routes_data + routes_data_records)..."
	docker-compose exec -T php php bin/console nowo:performance:sync-schema --no-interaction
	@echo "✅ Schema sync complete!"

# Performance Bundle: sync and drop columns no longer in entity
performance-sync-drop:
	@echo "🔄 Syncing Performance Bundle schema (with --drop-obsolete)..."
	docker-compose exec -T php php bin/console nowo:performance:sync-schema --drop-obsolete --no-interaction
	@echo "✅ Schema sync complete!"

# Show logs
logs:
	docker-compose logs -f

# Update bundle from path repo
update-bundle: ensure-up
	docker-compose exec -T php composer update nowo-tech/performance-bundle --no-interaction
	docker-compose exec -T php php bin/console cache:clear
	@echo "Bundle updated."

test: ensure-up
	docker-compose exec -T php composer test 2>/dev/null || docker-compose exec -T php php bin/console list

test-coverage: ensure-up
	docker-compose exec -T php composer test-coverage 2>/dev/null || docker-compose exec -T php composer test

# Clean vendor and cache
clean:
	@echo "🧹 Cleaning vendor, var, and cache..."
	rm -rf vendor
	rm -rf var/cache/*
	rm -rf var/log/*
	@echo "✅ Cleaned!"
