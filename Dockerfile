FROM php:8.4-fpm AS builder

# Build arguments (compile-time only)
ARG APP_ENV=production
ARG APP_DEBUG=false
ARG APP_URL=https://cs101.uk

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy composer files only
COPY composer.json composer.lock ./

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Install production dependencies
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --classmap-authoritative \
    --no-interaction \
    --prefer-dist

# Copy full source
COPY . .

# Create writable dirs
RUN mkdir -p cache database uploads \
    && touch database/database.sqlite \
    && chown -R www-data:www-data database cache uploads

# Entry script
COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Production PHP configs
COPY php_setup/prod/php.ini /usr/local/etc/php/php.ini
COPY php_setup/prod/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
