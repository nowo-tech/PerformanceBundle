# Performance Bundle – Demos

This directory contains demos for the Performance Bundle, one per supported Symfony major used for local verification.

## Demos

| Demo       | Symfony | Port (default) |
|-----------|---------|----------------|
| symfony8  | 8.0     | 8008           |

## Requirements

- Docker and Docker Compose
- Make (recommended)

## Quick start

From this directory:

```bash
make up-symfony8    # Start Symfony 8 demo
make setup-symfony8 # Install deps, create DB, load fixtures
# App: http://localhost:8008
```

Or from inside the demo (`cd symfony8`):

```bash
make up
make setup
```

See the demo README for details:

- [symfony8/README.md](symfony8/README.md)

## Commands (from demo/)

- `make help` – List all targets
- `make up-<demo>` / `make down-<demo>` – Start or stop a demo
- `make setup-<demo>` – Install, DB, schema, fixtures
- `make shell-<demo>` – Open shell in the container
