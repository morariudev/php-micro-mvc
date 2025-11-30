FROM php:8.4-fpm AS builder

ARG APP_ENV=production
ARG APP_DEBUG=false
ARG APP_URL=https://cs101.uk

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --prefer-dist

COPY . .

# Prepare dirs
RUN mkdir -p cache database uploads \
    && touch database/database.sqlite \
    && chown -R www-data:www-data /var/www/html

COPY ./docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Optional php.ini
# COPY php.ini /usr/local/etc/php/php.ini

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
