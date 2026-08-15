#!/bin/sh
set -eu

cd /var/www/html/apps/api

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  composer install --no-interaction
fi

php artisan key:generate --force
php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port=8000
