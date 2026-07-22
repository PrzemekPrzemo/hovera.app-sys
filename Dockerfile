# syntax=docker/dockerfile:1
#
# Multi-stage build for Coolify deployment on Hetzner. See
# docs/DEPLOY.md "Coolify (Hetzner)" for the full deployment guide.

# ---- Stage 1: PHP dependencies (needs full app source for autoload map) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-ansi \
    --prefer-dist

# ---- Stage 2: frontend assets (Vite/Tailwind) ----
# `npm install` not `npm ci` — no package-lock.json is committed in this
# repo today. Consider adding one for reproducible builds.
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json ./
RUN npm install
COPY . .
RUN npm run build

# ---- Stage 3: runtime image ----
FROM php:8.4-fpm-alpine AS runtime

# install-php-extensions (mlocati) instead of hand-rolled apk+docker-php-ext-install:
# it knows which extensions php:8.4-fpm-alpine already ships built-in (curl, json,
# ctype, session, tokenizer, openssl, sodium, dom/xml/simplexml, etc. — no-ops those
# safely) vs which genuinely need apk -dev packages + compiling, so this list can
# safely be "everything the app + its composer deps declare" without guessing.
# Full ext-* requirement list cross-checked against vendor/composer/installed.json.
RUN apk add --no-cache bash su-exec curl \
    && curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        mbstring \
        bcmath \
        intl \
        gd \
        zip \
        exif \
        opcache \
        curl \
        sodium

# opcache: sane production defaults (Coolify redeploys = new container, so
# no need for the OPcache-flush dance the Plesk `hu` script does today).
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
    } > /usr/local/etc/php/conf.d/opcache-hovera.ini

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/framework/testing/disks \
             storage/logs \
             storage/app/public \
             storage/app/exports \
             storage/app/private \
             bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Stays root here on purpose: php-fpm's own pool config (user=www-data,
# group=www-data, the image's default) drops its worker processes to
# www-data itself. The queue/scheduler roles don't go through php-fpm, so
# entrypoint.sh explicitly re-execs those via su-exec www-data instead.

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- Stage 4: nginx (built assets baked in at image-build time, not
# shared via a runtime volume — a named volume would only auto-populate
# once and go stale on every later redeploy that changes public/build) ----
FROM nginx:alpine AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
