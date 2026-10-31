FROM php:7.3-cli-alpine

# Install system dependencies and build tools
RUN apk add --no-cache \
    git \
    curl \
    unzip \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) pdo pdo_sqlite gd

# Configure git replacement for archived/moved dependency
RUN git config --global url."https://github.com/rrkhatri/Laravel-Intl.git".insteadOf "https://github.com/Propaganistas/Laravel-Intl.git"

# Install Composer 1.10.27 (compatible with Laravel 5.7 package manifest)
RUN curl -sS https://getcomposer.org/download/1.10.27/composer.phar -o /usr/local/bin/composer \
    && chmod +x /usr/local/bin/composer

WORKDIR /app

# Copy application code
COPY . .

# Run composer install and dump-autoload
RUN composer install --prefer-dist --no-interaction

# Ensure writable storage
RUN chmod -R 777 storage bootstrap/cache

CMD ["vendor/bin/phpunit"]
