# syntax=docker/dockerfile:1.7

# =====================================================================
# Stage 1 — Frontend build (Vite + React + TS + Tailwind)
# =====================================================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .
RUN npm run build

# =====================================================================
# Stage 2 — Composer dependencies (no dev)
# =====================================================================
FROM composer:2.8 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

# =====================================================================
# Stage 3 — Runtime (PHP-FPM 8.4 + extensions)
# =====================================================================
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        git \
        curl \
        zip \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        nginx \
        supervisor \
        tini \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/* /var/cache/apk/*

ENV TZ=America/Sao_Paulo
RUN cp /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/scripts/entrypoint.sh /usr/local/bin/entrypoint
COPY docker/scripts/wait-for-db.sh /usr/local/bin/wait-for-db
RUN chmod +x /usr/local/bin/entrypoint /usr/local/bin/wait-for-db

RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && mkdir -p storage/app/{public,downloads,private} \
                storage/framework/{cache/data,sessions,testing,views} \
                storage/logs \
                bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]
CMD ["app"]
