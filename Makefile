# Makefile for Performance Bundle
# Simplifies Docker commands for development

.PHONY: help up down shell install test test-coverage cs-check cs-fix qa clean setup-hooks test-up test-down test-shell ensure-up assets release-check release-check-demos composer-sync

# Default target
help:
	@echo "Performance Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  test          Run PHPUnit tests only (no coverage). Starts PHP container if needed."
	@echo "  test-coverage Run PHPUnit tests with code coverage (HTML + Clover). Starts PHP container if needed."
	@echo "  test-with-db  Run tests with databases (integration tests)"
	@echo "  test-coverage-with-db Run tests with coverage and databases"
	@echo "  test-up       Start test container with databases"
	@echo "  test-down     Stop test container"
	@echo "  test-shell    Open shell in test container"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  release-check Pre-release: cs-fix, cs-check, test-coverage, demo healthchecks"
	@echo "  composer-sync Validate composer.json and align composer.lock (no install)"
	@echo "  clean         Remove vendor and cache"
	@echo "  setup-hooks   Install git pre-commit hooks"
	@echo "  validate-translations  Validate YAML translation files in PHP container (starts container if needed)"
	@echo "  assets        No frontend assets in this bundle (no-op)"
	@echo ""

# Build and start containers (php + mysql + postgres)
up:
	@echo "Building Docker image..."
	docker-compose build
	@echo "Starting containers (PHP, MySQL, PostgreSQL)..."
	docker-compose up -d
	@echo "Waiting for databases to be ready..."
	@sleep 10
	@echo "Installing dependencies..."
	docker-compose exec -T php composer install --no-interaction
	@echo "✅ Containers ready!"

# Stop container
down:
	docker-compose down

# Ensure container is running (start if not). Used by install, shell, test, cs-check, etc.
ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		docker-compose up -d; \
		sleep 5; \
	fi

# Open shell in container
shell: ensure-up
	docker-compose exec php sh

# Install dependencies
install: ensure-up
	docker-compose exec -T php composer install

# Run tests only (no coverage). Starts PHP container if needed.
test: ensure-up
	docker-compose exec -T php composer test

# Run tests with code coverage (HTML in coverage/, Clover in coverage.xml). Starts PHP container if needed.
test-coverage: ensure-up
	docker-compose exec -T php composer test-coverage

# Run tests with databases (same compose: php + mysql + postgres)
test-with-db: ensure-up
	docker-compose exec -T php composer test

# Run tests with coverage and databases (PCOV in image)
test-coverage-with-db: ensure-up
	docker-compose exec -T php composer test-coverage

# Start containers (same as up)
test-up:
	$(MAKE) up

# Stop containers
test-down:
	docker-compose down

# Open shell in php container
test-shell:
	docker-compose exec php sh

# Check code style
cs-check: ensure-up
	docker-compose exec -T php composer cs-check

# Fix code style
cs-fix: ensure-up
	docker-compose exec -T php composer cs-fix

# Run all QA
qa: ensure-up
	docker-compose exec -T php composer qa

# Pre-release: cs-fix, cs-check, test-coverage, demo healthchecks
release-check: ensure-up composer-sync cs-fix cs-check test-coverage release-check-demos

release-check-demos:
	@$(MAKE) -C demo release-verify

composer-sync: ensure-up
	docker-compose exec -T php composer validate --strict
	docker-compose exec -T php composer update --no-install

# Clean vendor and cache
clean:
	rm -rf vendor
	rm -rf .phpunit.cache
	rm -rf coverage
	rm -f coverage.xml
	rm -f .php-cs-fixer.cache

# Validate YAML translation files (duplicate keys, syntax if ext-yaml available). Starts PHP container if needed.
validate-translations: ensure-up
	docker-compose exec -T php php scripts/validate-translations-yaml.php src/Resources/translations

# No frontend assets in this bundle
assets:
	@echo "No frontend assets in this bundle."

# Setup git hooks for pre-commit checks
setup-hooks:
	@if [ -d .githooks ]; then \
		chmod +x .githooks/pre-commit; \
		git config core.hooksPath .githooks; \
		echo "✅ Git hooks installed! CS-check and tests will run before each commit."; \
	else \
		echo "⚠️  .githooks directory not found, skipping hook setup"; \
	fi
