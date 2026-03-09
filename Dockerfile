FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install zip pdo pdo_mysql pdo_pgsql \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files (lock optional so build works when lock is out of date)
COPY composer.json composer.lock* ./

# Install dependencies; if lock is invalid, update to resolve
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || composer update --no-interaction --prefer-dist --optimize-autoloader

# Copy application files
COPY . .

# Default command
CMD ["tail", "-f", "/dev/null"]
