# Humoo API

Laravel 13 lives in this directory and runs through Docker in the current
Windows environment.

## Verified on August 14, 2026

- `GET /api/v1/health`
- `php artisan test`
- `php artisan db:seed --force`

## Run through Docker

```bash
docker compose up --build -d
docker compose exec -T api php artisan test
docker compose exec -T api php artisan db:seed --force
```

## Current foundation

- Laravel 13
- Sanctum installed
- MySQL 8.4 in Docker
- Request ID middleware
- Health endpoint with normalized payload
- Initial multitenant schema and demo seeder
