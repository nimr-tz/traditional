FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY components.json eslint.config.js .prettierignore .prettierrc tsconfig.json vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build


FROM frontend AS frontend-quality

RUN npm run format \
    && npm run lint:check


FROM php:8.4-fpm AS runtime

WORKDIR /var/www

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git curl unzip \
        libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev \
        libpng-dev libsqlite3-dev libxml2-dev libzip-dev zlib1g-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath exif gd intl mbstring opcache pcntl pdo_mysql pdo_sqlite zip; \
    apt-get purge -y --auto-remove $PHPIZE_DEPS; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private/presentations \
        storage/app/private/student-verification \
        storage/framework/cache/data \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]


FROM runtime AS test

USER root
RUN cp .env.example .env \
    && composer install \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    && chown -R www-data:www-data /var/www

USER www-data
CMD ["php", "artisan", "test"]
