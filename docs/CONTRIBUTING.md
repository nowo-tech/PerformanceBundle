# Contributing

Thank you for considering contributing to Performance Bundle!



## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Maintainer](#maintainer)
- [Development Setup](#development-setup)
  - [Using Docker (Recommended)](#using-docker-recommended)
  - [Without Docker](#without-docker)
- [Branching Strategy](#branching-strategy)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Running Tests](#running-tests)
  - [With Docker](#with-docker)
  - [Without Docker](#without-docker)
- [Available Make Commands](#available-make-commands)
- [Git hooks (REQ-GIT-001)](#git-hooks-req-git-001)
- [Reporting Issues](#reporting-issues)
- [Contact](#contact)

## Maintainer

This project is maintained by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech).

## Development Setup

### Using Docker (Recommended)

1. Clone the repository:
   ```bash
   git clone https://github.com/nowo-tech/PerformanceBundle.git
   cd performance-bundle
   ```

2. Start the Docker containers:
   ```bash
   make up
   ```

3. Install dependencies:
   ```bash
   make install
   ```

4. Run tests:
   ```bash
   make test
   ```

5. Open a shell in the container (optional):
   ```bash
   make shell
   ```

### Without Docker

1. Clone the repository:
   ```bash
   git clone https://github.com/nowo-tech/PerformanceBundle.git
   cd performance-bundle
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   composer test
   ```

## Branching Strategy

We follow a simplified Git Flow. See [BRANCHING.md](BRANCHING.md) for full details.

| Branch | Purpose |
|--------|---------|
| `main` | Production releases only |
| `develop` | Development integration |
| `feature/*` | New features |
| `bugfix/*` | Bug fixes |
| `hotfix/*` | Urgent production fixes |

## Pull Request Process

1. Fork the repository
2. Create a branch from `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/amazing-feature
   ```
3. Make your changes
4. Run tests and code style checks:
   ```bash
   make qa
   # or without Docker:
   composer qa
   ```
5. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/):
   ```bash
   git commit -m 'feat(scope): add amazing feature'
   ```
6. Push to the branch:
   ```bash
   git push origin feature/amazing-feature
   ```
7. Open a Pull Request **to `develop`** (not `main`)

## Coding Standards

- Follow PSR-12 coding style
- Use **English** for comments, PHPDoc, and test docblocks in `src/` and `tests/`
- Add tests for new features
- Update documentation as needed
- Keep commits atomic and descriptive

## Running Tests

### With Docker

```bash
# Run all tests
make test

# Run tests with coverage
make test-coverage

# Optional: fail if coverage is below 90% (after PHPUnit source excludes)
make test-coverage-90

# Check code style
make cs-check

# Fix code style
make cs-fix

# Run all QA checks
make qa
```

### Without Docker

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Optional: fail if coverage is below 90%
composer test-coverage-90

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## Available Make Commands

| Command | Description |
|---------|-------------|
| `make up` | Start Docker container |
| `make down` | Stop Docker container |
| `make shell` | Open shell in container |
| `make install` | Install Composer dependencies |
| `make test` | Run PHPUnit tests (unit tests only) |
| `make test-coverage` | Run tests with code coverage (unit tests only) |
| `make test-coverage-90` | Run coverage then fail if statements/elements are below 90% |
| `make test-with-db` | Run tests with databases (integration tests) |
| `make test-coverage-with-db` | Run tests with coverage and databases |
| `make test-up` | Start test container with databases |
| `make test-down` | Stop test container |
| `make test-shell` | Open shell in test container |
| `make cs-check` | Check code style (PSR-12) |
| `make cs-fix` | Fix code style |
| `make qa` | Run all QA checks (cs-check + test) |
| `make clean` | Remove vendor and cache |
| `make setup-hooks` | Install git pre-commit hooks |

## Reporting Issues

When reporting issues, please include:
- PHP version
- Symfony version
- Doctrine ORM version
- Database type and version (MySQL, PostgreSQL, etc.)
- Operating system
- Steps to reproduce
- Expected vs actual behavior
- Relevant entity/attribute configuration (if applicable)

## Contact

For questions or suggestions, you can reach out to:
- GitHub: [@HecFranco](https://github.com/HecFranco)
- Organization: [nowo-tech](https://github.com/nowo-tech)

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.

CI checks **new commits on each push/PR** (not the full historical log). To purge legacy trailers from `main`, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.
