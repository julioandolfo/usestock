#!/usr/bin/env bash
# Unified entrypoint for all container roles (app, worker, scheduler, reverb, migrate).
# The container role is selected via the CMD: app | worker | scheduler | reverb | migrate | artisan.
set -euo pipefail

cd /var/www/html

# ---------------------------------------------------------------------
# Bootstrap APP_KEY: persisted on a shared volume so containers restarts
# (and multiple replicas) reuse the same key without a manual .env step.
# ---------------------------------------------------------------------
KEY_FILE="/var/www/html/storage/app.key"

if [[ -z "${APP_KEY:-}" ]]; then
    if [[ -f "${KEY_FILE}" ]]; then
        APP_KEY="$(cat "${KEY_FILE}")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        echo "${APP_KEY}" > "${KEY_FILE}"
        chmod 600 "${KEY_FILE}"
        echo "[entrypoint] generated new APP_KEY and persisted to ${KEY_FILE}"
    fi
    export APP_KEY
fi

# ---------------------------------------------------------------------
# Storage permissions (Coolify volumes may come up root-owned)
# ---------------------------------------------------------------------
mkdir -p \
    storage/app/public \
    storage/app/downloads \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Ensure storage symlink exists
if [[ ! -L public/storage ]]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

ROLE="${1:-app}"
shift || true

case "${ROLE}" in
    app)
        /usr/local/bin/wait-for-db
        php artisan migrate --force --no-interaction || true
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        php artisan event:cache
        exec supervisord -c /etc/supervisord.conf
        ;;

    worker)
        /usr/local/bin/wait-for-db
        exec php artisan horizon
        ;;

    scheduler)
        /usr/local/bin/wait-for-db
        # Loop schedule:run every 60s — lighter than supervisord+cron for one job.
        exec php artisan schedule:work --no-interaction
        ;;

    reverb)
        /usr/local/bin/wait-for-db
        exec php artisan reverb:start --host=0.0.0.0 --port=8080 --no-interaction
        ;;

    migrate)
        /usr/local/bin/wait-for-db
        php artisan migrate --force --no-interaction
        exit 0
        ;;

    artisan)
        exec php artisan "$@"
        ;;

    sh|bash)
        exec /bin/bash
        ;;

    *)
        echo "[entrypoint] unknown role: ${ROLE}" >&2
        echo "Usage: app | worker | scheduler | reverb | migrate | artisan <cmd> | sh" >&2
        exit 1
        ;;
esac
