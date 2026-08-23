#!/bin/sh
set -eu

cd /var/www/html/apps/api

if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
fi

if [ "${HUMOO_RUN_MIGRATIONS_ON_START:-0}" = "1" ]; then
  if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required before running startup migrations." >&2
    exit 1
  fi

  php artisan migrate --force
fi

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
