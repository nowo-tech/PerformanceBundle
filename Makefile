# Makefile for Performance Bundle
# Simplifies Docker commands for development

.PHONY: help up down build shell install test test-coverage coverage-php-percent test-coverage-90 test-coverage-100 cs-check cs-fix qa clean assets setup-hooks ensure-up rector rector-dry phpstan release-check release-check-demos composer-sync update validate test-with-db test-coverage-with-db validate-translations

# Default target
help:
	@echo "Performance Bundle - Development Commands"
	@echo ""
	@echo "Usage: make <target>"
	@echo ""
	@echo "Targets:"
	@echo "  up            Start Docker container"
	@echo "  down          Stop Docker container"
	@echo "  build         Rebuild Docker image (no cache)"
	@echo "  shell         Open shell in container"
	@echo "  install       Install Composer dependencies"
	@echo "  assets        No frontend assets in this bundle (no-op)"
	@echo "  test          Run PHPUnit tests (starts container if needed)"
	@echo "  test-coverage Run PHPUnit tests with code coverage (HTML + Clover)"
	@echo "  test-coverage-90  Run test-coverage and fail if coverage < 90%"
	@echo "  test-coverage-100 Run test-coverage and fail if coverage < 100%"
	@echo "  test-with-db  Run tests with databases (same compose: php + MySQL + PostgreSQL)"
	@echo "  test-coverage-with-db  Run tests with coverage and databases"
	@echo "  cs-check      Check code style"
	@echo "  cs-fix        Fix code style"
	@echo "  rector        Apply Rector refactoring"
	@echo "  rector-dry    Run Rector in dry-run mode"
	@echo "  phpstan       Run PHPStan static analysis"
	@echo "  qa            Run all QA checks (cs-check + test)"
	@echo "  release-check Pre-release: cs-fix, cs-check, rector-dry, phpstan, test-coverage, demo healthchecks"
	@echo "  composer-sync Validate composer.json and align composer.lock"
	@echo "  clean         Remove vendor and cache"
	@echo "  update        Update composer.lock (composer update)"
	@echo "  validate      Run composer validate --strict"
	@echo ""
	@echo "Bundle-specific:"
	@echo "  test-with-db  Run tests with databases (integration tests; same compose as up)"
	@echo "  test-coverage-with-db  Run tests with coverage and databases"
	@echo "  validate-translations  Validate YAML translation files in PHP container"
	@echo "  setup-hooks   Install git pre-commit hooks"
	@echo ""
	@echo "Demos:"
	@echo "  (use make -C demo or make -C demo/symfonyX)"
	@echo ""

# Rebuild Docker image (no cache)
build:
	docker-compose build --no-cache

# Build and start containers (php + mysql + postgres)
up:
	@echo "Building Docker image..."
	docker-compose build
	@echo "Starting containers (PHP, MySQL, PostgreSQL)..."
	docker-compose up -d
	@echo "Waiting for databases to be ready..."
	@sleep 10
	@echo "Installing dependencies..."
	docker-compose exec -T php sh -c "composer install --no-interaction || composer update --no-interaction"
	@echo "✅ Container ready!"

# Stop container
down:
	docker-compose down

# Ensure root container is running (start if not). Used by cs-fix, cs-check, qa, install, test, test-coverage, validate-translations.
# Also sets git safe.directory /app inside the container so Composer can read the repo (avoids "dubious ownership" when bind-mounting).
ensure-up:
	@if ! docker-compose exec -T php true 2>/dev/null; then \
		echo "Starting container (root docker-compose)..."; \
		docker-compose up -d; \
		sleep 3; \
		docker-compose exec -T php sh -c "composer install --no-interaction || composer update --no-interaction"; \
	fi
	@docker-compose exec -T php git config --global --add safe.directory /app 2>/dev/null || true

# Open shell in container
shell: ensure-up
	docker-compose exec php sh

# Install dependencies
install: ensure-up
	docker-compose exec -T php composer install

# Run tests only (no coverage). Starts PHP container if needed.
# Run tests (no -T so TTY is allocated and PHPUnit can show colors in console)
test: ensure-up
	docker-compose exec php composer test

# Run tests with code coverage (no -T so coverage is shown in console with colors).
# --process-isolation avoids truncated output; @runInSeparateProcess tests and non-TTY pipes can otherwise
# end the run before the report is printed (see PHPUnit issue #5993).
test-coverage: ensure-up
	docker-compose exec php composer test-coverage | tee coverage-php.txt
	./.scripts/php-coverage-percent.sh coverage-php.txt

# Run test-coverage and fail if coverage is below 90%
test-coverage-90: ensure-up
	docker-compose exec php composer test-coverage-90

# Run test-coverage and fail if coverage is below 100% (requires coverage.xml from test-coverage)
test-coverage-100: ensure-up
	docker-compose exec php composer test-coverage
	docker-compose exec -T php php scripts/check-coverage.php coverage.xml --min-percent=100

# Run tests with databases (php + MySQL + PostgreSQL in same compose; §2.2 single docker-compose)
test-with-db: ensure-up
	docker-compose exec -T php composer test

# Run tests with coverage and databases (same compose)
test-coverage-with-db: ensure-up
	docker-compose exec php composer test-coverage

# Check code style
cs-check: ensure-up
	docker-compose exec -T php composer cs-check

# Fix code style
cs-fix: ensure-up
	docker-compose exec -T php composer cs-fix

rector: ensure-up
	docker-compose exec -T php composer rector

rector-dry: ensure-up
	docker-compose exec -T php composer rector-dry

phpstan: ensure-up
	docker-compose exec -T php composer phpstan

# Run all QA
qa: ensure-up
	docker-compose exec -T php composer qa

composer-sync: ensure-up
	docker-compose exec -T php composer validate --strict
	docker-compose exec -T php composer update --no-install

# Update composer.lock
update: ensure-up
	docker-compose exec -T php composer deps-update

# Validate composer.json
validate: ensure-up
	docker-compose exec -T php composer deps-validate

release-check: ensure-up composer-sync cs-fix cs-check rector-dry phpstan test-coverage release-check-demos

release-check-demos:
	@$(MAKE) -C demo release-check

# No frontend assets in this bundle (single recipe to avoid Make warning)
assets:
	@echo "No frontend assets in this bundle."

# Validate YAML translation files (duplicate keys, syntax if ext-yaml available). Starts PHP container if needed.
validate-translations: ensure-up
	docker-compose exec -T php php scripts/validate-translations-yaml.php src/Resources/translations

# Setup git hooks for pre-commit checks
setup-hooks:
	@if [ -d .githooks ]; then \
		chmod +x .githooks/pre-commit; \
		git config core.hooksPath .githooks; \
		echo "✅ Git hooks installed! CS-check and tests will run before each commit."; \
	else \
		echo "⚠️  .githooks directory not found, skipping hook setup"; \
	fi
