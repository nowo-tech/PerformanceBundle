# FrankenPHP (Dunglas) - PHP 8.4 Alpine
# https://frankenphp.dev/docs/docker/
FROM dunglas/frankenphp:1-php8.4-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    bash \
    curl \
    ca-certificates

# Install PHP extensions (Doctrine ORM + MySQL)
RUN install-php-extensions zip pdo_mysql intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure git safe directory
RUN git config --global --add safe.directory /app && \
    git config --global --add safe.directory /var/performance-bundle

WORKDIR /app

# Caddyfile: HTTP on :80, runtime worker. Caddyfile.dev used when APP_ENV=dev.
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80

# Entrypoint: when APP_ENV=dev copy Caddyfile.dev (no worker), ensure var dirs, then run FrankenPHP
RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ "${APP_ENV:-prod}" = "dev" ]; then cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'mkdir -p /app/var/cache /app/var/log' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 777 /app/var' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
