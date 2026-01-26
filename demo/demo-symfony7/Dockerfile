FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install zip pdo pdo_mysql pdo_pgsql intl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Set environment
ENV COMPOSER_ALLOW_SUPERUSER=1
