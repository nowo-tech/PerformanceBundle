# Performance Bundle – Demos

This directory contains demos for the Performance Bundle, one per supported Symfony version.

## Demos

| Demo       | Symfony | Port (default) |
|-----------|---------|----------------|
| symfony7  | 7.0     | 8007           |
| symfony8  | 8.0     | 8008           |

## Requirements

- Docker and Docker Compose
- Make (recommended)

## Quick start

From this directory:

```bash
make up-symfony7    # Start Symfony 7 demo
make setup-symfony7 # Install deps, create DB, load fixtures
# App: http://localhost:8007

make up-symfony8    # Start Symfony 8 demo
make setup-symfony8
# App: http://localhost:8008
```

Or from inside a demo (e.g. `cd symfony8`):

```bash
make up
make setup
```

See each demo's README for details:

- [symfony7/README.md](symfony7/README.md)
- [symfony8/README.md](symfony8/README.md)

## Commands (from demo/)

- `make help` – List all targets
- `make up-<demo>` / `make down-<demo>` – Start or stop a demo
- `make setup-<demo>` – Install, DB, schema, fixtures
- `make shell-<demo>` – Open shell in the container
