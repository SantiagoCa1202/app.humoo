#!/bin/sh
set -eu

cd /var/www/html/apps/api

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

if [ "${HUMOO_RUN_MIGRATIONS_ON_START:-0}" = "1" ]; then
  php artisan key:generate --force
  php artisan migrate --force
fi

exec php artisan serve --host=0.0.0.0 --port=8000
