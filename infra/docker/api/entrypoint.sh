#!/bin/sh
set -eu

cd /var/www/html/apps/api

if [ ! -f .env ]; then
  echo "Missing apps/api/.env; refusing to bootstrap runtime configuration." >&2
  exit 1
fi

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

if [ "${HUMOO_RUN_MIGRATIONS_ON_START:-0}" = "1" ]; then
  if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required before running startup migrations." >&2
    exit 1
  fi

  php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000
