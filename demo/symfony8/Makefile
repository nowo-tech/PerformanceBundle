# Makefile for Performance Bundle Demo - Symfony 8
# Simplifies Docker commands for the demo

.PHONY: help up down shell install clean setup

# Default target
help:
	@echo "Performance Bundle Demo - Symfony 8 - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker containers (PHP, MySQL, Nginx)"
	@echo "  down          Stop Docker containers"
	@echo "  shell         Open shell in PHP container"
	@echo "  install       Install Composer dependencies"
	@echo "  setup         Complete setup (install + DB + fixtures)"
	@echo "  clean         Remove vendor, var, and cache"
	@echo "  logs          Show container logs"
	@echo ""
	@echo "Database:"
	@echo "  db-create     Create database"
	@echo "  db-drop       Drop database"
	@echo "  db-reset      Reset database (drop + create + schema + fixtures)"
	@echo "  db-fixtures   Load fixtures"
	@echo "  db-view       View performance metrics"
	@echo ""

# Build and start containers
up:
	@echo "🚀 Starting demo containers..."
	@if [ ! -f .env ]; then \
		echo "⚠️  .env file not found. Creating from defaults..."; \
		echo "APP_ENV=dev" > .env; \
		echo "APP_DEBUG=1" >> .env; \
		echo "APP_SECRET=your-secret-key-change-this-in-production" >> .env; \
		echo "DATABASE_URL=mysql://demo_user:password@mysql:3306/performance_demo?serverVersion=8.0&charset=utf8mb4" >> .env; \
		echo "MYSQL_ROOT_PASSWORD=password" >> .env; \
		echo "MYSQL_DATABASE=performance_demo" >> .env; \
		echo "MYSQL_USER=demo_user" >> .env; \
		echo "MYSQL_PASSWORD=password" >> .env; \
		echo "✅ .env file created"; \
	fi
	docker-compose build
	docker-compose up -d
	@echo "⏳ Waiting for MySQL to be ready..."
	@sleep 5
	@echo "✅ Containers started!"
	@echo ""
	@echo "Next steps:"
	@echo "  make install    - Install dependencies"
	@echo "  make setup      - Complete setup (install + DB + fixtures)"

# Stop containers
down:
	@echo "🛑 Stopping containers..."
	docker-compose down
	@echo "✅ Containers stopped!"

# Open shell in PHP container
shell:
	docker-compose exec php sh

# Install dependencies
install:
	@echo "📦 Installing dependencies..."
	docker-compose exec -T php composer install --no-interaction
	@echo "✅ Dependencies installed!"

# Complete setup
setup:
	@echo "🔧 Setting up demo..."
	@echo "Waiting for MySQL to be ready..."
	@sleep 3
	@echo "Installing dependencies..."
	-docker-compose exec -T php composer install --no-interaction --ignore-platform-reqs 2>/dev/null || docker-compose exec -T php composer install --no-interaction
	@echo "Creating database..."
	-docker-compose exec -T php php bin/console doctrine:database:create --if-not-exists --no-interaction
	@echo "Creating schema..."
	-docker-compose exec -T php php bin/console doctrine:schema:create --no-interaction
	@echo "Loading fixtures..."
	-docker-compose exec -T php php bin/console doctrine:fixtures:load --no-interaction
	@echo "✅ Demo setup complete!"
	@echo ""
	@echo "Access the application at: http://localhost:8000"
	@echo "phpMyAdmin at: http://localhost:8080"

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

# Show logs
logs:
	docker-compose logs -f

# Clean vendor and cache
clean:
	@echo "🧹 Cleaning vendor, var, and cache..."
	rm -rf vendor
	rm -rf var/cache/*
	rm -rf var/log/*
	@echo "✅ Cleaned!"
