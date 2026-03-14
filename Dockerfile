# PHP 8.2 Alpine for Performance Bundle (dev and tests; includes pdo_mysql/pdo_pgsql for integration tests)
FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    bash \
    libzip-dev \
    postgresql-dev

RUN docker-php-ext-install -j$(nproc) zip pdo pdo_mysql pdo_pgsql

# PCOV for code coverage
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files (lock optional so build works when lock is out of date)
COPY composer.json composer.lock* ./

# Install dependencies; if lock is invalid, update to resolve
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || composer update --no-interaction --prefer-dist --optimize-autoloader

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
