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
# Stage 2 — PHP base with all runtime extensions
# Shared by `vendor` and `runtime` so Composer resolves against the same
# platform the app will actually run on.
# =====================================================================
# Pinned to a specific PHP patch tag so `docker build --pull` (Coolify's
# default) can't silently swap in a base image that breaks the extension
# install step.
FROM php:8.4.13-fpm-alpine3.22 AS php-base

# mlocati's installer handles Alpine deps + non-interactive pecl prompts
# (igbinary/lzf/zstd) that break plain `pecl install redis`. Pinned to a
# known-good release so a bad upstream `latest` can't take our deploy down.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.9.34/install-php-extensions /usr/local/bin/

# Base OS packages first — cheap layer, changes rarely.
RUN apk add --no-cache bash git curl zip unzip

# Split the extension install per extension so a future failure points at
# exactly which one broke (Coolify's build log only shows the failing RUN).
RUN install-php-extensions bcmath
RUN install-php-extensions intl
RUN install-php-extensions opcache
RUN install-php-extensions pcntl
RUN install-php-extensions pdo_pgsql
RUN install-php-extensions pgsql
RUN install-php-extensions zip
RUN install-php-extensions gd
RUN install-php-extensions redis

RUN rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

# =====================================================================
# Stage 3 — Composer dependencies (no dev)
# =====================================================================
FROM php-base AS vendor
WORKDIR /app

ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

# =====================================================================
# Stage 4 — Runtime (PHP-FPM 8.4 + nginx + supervisord)
# =====================================================================
FROM php-base AS runtime

RUN apk add --no-cache nginx supervisor tini tzdata bind-tools \
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
