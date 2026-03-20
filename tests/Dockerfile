FROM php:8.2-cli

RUN apt-get update && apt-get install -y libpq-dev unzip git \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug && docker-php-ext-enable xdebug

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist

COPY . .
