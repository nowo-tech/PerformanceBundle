# Demo applications with FrankenPHP (development and production)

This document describes how the bundle's demo applications run under **FrankenPHP** in Docker, and how to reproduce **development** (no cache, changes visible on refresh) and **production** (worker mode, cache enabled) configurations. The same approach can be used in other Symfony bundles or applications that ship a FrankenPHP-based demo.

## Contents

- [Overview](#overview)
- [What the demos include](#what-the-demos-include)
- [Development configuration](#development-configuration)
- [Production configuration](#production-configuration)
- [Switching between development and production](#switching-between-development-and-production)
- [Reproducing in another bundle](#reproducing-in-another-bundle)
- [Troubleshooting](#troubleshooting)

---

## Overview

**The `demo/` folder is not shipped when the bundle is installed** (e.g. via `composer require nowo-tech/performance-bundle`). It is excluded from the Composer package (via `archive.exclude` in the bundle's `composer.json`). The demo applications exist only in the bundle's source repository and are intended for development, testing, and documentation. To run or modify the demos, use a clone of the bundle repository.

The demos use:

- **FrankenPHP** (Caddy + PHP) in a single container.
- **Docker Compose** with the app and the parent bundle mounted as volumes (`../..` → `/var/performance-bundle`). **MySQL** service is included; keep it for demos.
- **Two Caddyfiles**: `Caddyfile` (production, with worker) and `Caddyfile.dev` (development, no worker).
- An **entrypoint** that, when `APP_ENV=dev`, copies `Caddyfile.dev` over the default Caddyfile and then starts FrankenPHP.

There are demos for **Symfony 7** and **Symfony 8** (e.g. **demo/symfony7**, **demo/symfony8**). Each has its own Dockerfile, docker-compose.yml and Makefile. From the bundle root you run e.g. `make -C demo/symfony8 up` (see the demo's README for the URL and port).

The main difference between development and production is:

| Aspect | Development | Production |
|--------|-------------|------------|
| FrankenPHP worker mode | **Off** (one PHP process per request) | **On** (workers keep app in memory) |
| Twig cache | **Off** (`config/packages/dev/twig.yaml`) | **On** (default) |
| OPcache revalidation | Every request (`docker/php-dev.ini`) | Default (e.g. 2 seconds) |
| HTTP cache headers | `no-store`, `no-cache` (in Caddyfile.dev) | Omitted or cache-friendly |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |

**Ports:** Each demo uses `PORT` from its `.env`. MySQL is kept; ensure DATABASE_URL and related env vars are set.

---

## What the demos include

The demo applications are configured for **local development and debugging**:

- **Symfony Web Profiler** and **Debug bundle** — enabled in `dev` and `test` environments.
- **Performance Bundle** (`Nowo\PerformanceBundle\NowoPerformanceBundle`) and **Twig Inspector** — the bundles under test; enabled in the demos.

Example `config/bundles.php` (aligned with **demo/symfony8**; Symfony 7 demo is similar):

```php
<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class        => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class          => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class       => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                  => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class               => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class   => ['dev' => true, 'test' => true],
    Nowo\PerformanceBundle\NowoPerformanceBundle::class          => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class        => ['all' => true],
    Symfony\UX\Icons\UXIconsBundle::class                      => ['all' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class      => ['dev' => true, 'test' => true],
];
```

In **production** (`APP_ENV=prod`), only bundles registered for `all` or `prod` are loaded.

---

## Development configuration

Goal: every change to PHP, Twig or config is visible on the next browser refresh without restarting the container. No long-lived PHP workers; cache disabled or revalidated on every request.

### 1. Caddyfile (development)

The development Caddyfile is **docker/frankenphp/Caddyfile.dev** in each demo. It uses plain `php_server` (no worker) and cache-busting headers. The entrypoint copies it over `/etc/frankenphp/Caddyfile` when `APP_ENV=dev`. Mount it in docker-compose so you can edit it without rebuilding.

### 2. PHP configuration (development)

The demos include **docker/php-dev.ini** with `opcache.revalidate_freq=0`. Mount it in docker-compose: `./docker/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini:ro`.

### 3. Twig configuration (development)

The demos use **config/packages/dev/twig.yaml** with `twig.cache: false` so template changes are visible on refresh.

### 4. Docker Compose (development)

Each demo's **docker-compose.yml** sets `APP_ENV=dev` and `APP_DEBUG=1`, and mounts the app, the bundle (`../..:/var/performance-bundle`), `docker/frankenphp/Caddyfile.dev`, and `docker/php-dev.ini`. The entrypoint copies Caddyfile.dev when `APP_ENV=dev`. MySQL service and DATABASE_URL are kept.

### 5. Start the demo (development)

From the bundle root: `make -C demo/symfony8 up` (or `make -C demo/symfony7 up`). Or from the demo directory: `make up`.

---

## Production configuration

Use the default Caddyfile (with worker). Set `APP_ENV=prod` and `APP_DEBUG=0`. Do not mount `php-dev.ini`. See [TwigInspectorBundle DEMO-FRANKENPHP](https://github.com/nowo-tech/TwigInspectorBundle/blob/main/docs/DEMO-FRANKENPHP.md) for the full production Caddyfile and steps.

---

## Switching between development and production

- **Development:** `APP_ENV=dev`, `APP_DEBUG=1`. Entrypoint copies Caddyfile.dev (no worker, no-cache headers). Mount php-dev.ini and dev twig cache off.
- **Production:** `APP_ENV=prod`, `APP_DEBUG=0`. Entrypoint leaves default Caddyfile (with worker). Do not mount php-dev.ini.

After changing env or Caddyfile, restart: `docker-compose restart` or `make -C demo/symfony8 restart`.

---

## Reproducing in another bundle

See [TwigInspectorBundle DEMO-FRANKENPHP](https://github.com/nowo-tech/TwigInspectorBundle/blob/main/docs/DEMO-FRANKENPHP.md) section "Reproducing in another bundle" for the full checklist.

---

## Troubleshooting

- **Changes not visible:** Ensure worker mode is off in dev (Caddyfile.dev has no `worker`), add dev twig.yaml and php-dev.ini, restart container, hard-refresh browser.
- **Web Profiler not visible:** Check `APP_ENV=dev` and `APP_DEBUG=1`, and that WebProfilerBundle is enabled for `dev` in bundles.php.
- **Demo times out:** Check port is free, container logs (`docker-compose logs php`), and required env vars (e.g. APP_SECRET). Ensure MySQL is healthy.
