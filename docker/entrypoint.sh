#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Ensure storage subdirs exist with correct ownership every boot — a fresh
# named volume mounted at storage/ (see docker-compose.yml) starts empty
# and hides whatever the image's Dockerfile mkdir'd at build time.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/framework/testing/disks \
         storage/logs \
         storage/app/public \
         storage/app/exports \
         storage/app/private \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Three services (web/queue/scheduler) share this exact image — only the
# `web` role runs migrations/cache-rebuild on boot, so a rolling redeploy
# never runs `tenants:migrate` three times concurrently. See
# docs/DEPLOY.md "Coolify (Hetzner)".
ROLE="${CONTAINER_ROLE:-web}"

if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] FATAL: APP_KEY is not set."
    echo "[entrypoint]   Existing prod key must be reused (it encrypts sessions"
    echo "[entrypoint]   and tenant DB credentials) — do not generate a new one."
    echo "[entrypoint]   Only for a brand-new deployment, generate with:"
    echo "[entrypoint]     printf 'base64:%s\n' \"\$(openssl rand -base64 32)\""
    exit 1
fi

# Stub .env so `artisan about`/`tinker` find a file even though real config
# arrives as Docker env vars (Coolify), not a committed .env.
[ -f .env ] || touch .env

if [ "$ROLE" = "web" ]; then
    echo "[entrypoint] role=web — waiting for MySQL..."
    until php -r '
        try {
            new PDO(
                "mysql:host=".getenv("DB_CENTRAL_HOST").";port=".getenv("DB_CENTRAL_PORT"),
                getenv("DB_CENTRAL_USERNAME"),
                getenv("DB_CENTRAL_PASSWORD")
            );
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        echo "[entrypoint] MySQL not ready yet, retrying in 2s..."
        sleep 2
    done

    echo "[entrypoint] role=web — running migrations + cache rebuild"
    php artisan migrate --force
    php artisan tenants:migrate
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan filament:cache-components
    php artisan storage:link || true
else
    echo "[entrypoint] role=$ROLE — skipping migrations/cache (owned by the web service)"
fi

if [ "$ROLE" = "web" ]; then
    # php-fpm's own pool config (user=www-data) drops its worker processes;
    # the master can stay root.
    exec "$@"
else
    # queue/scheduler run `php artisan ...` directly, not through php-fpm —
    # drop to www-data ourselves before exec'ing.
    exec su-exec www-data "$@"
fi
