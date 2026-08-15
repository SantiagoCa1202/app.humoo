# Humoo

Humoo is a universal frontend and backend foundation for a conversational
kitchen operations platform.

## Current status

- `apps/client`: implemented and verified with TypeScript and web export.
- `apps/api`: Laravel 13 scaffolded, Dockerized, and verified with health and tests.

## Repository structure

```text
app.humoo/
├── apps/
│   ├── api/
│   └── client/
├── docs/
├── .github/workflows/
├── .editorconfig
├── .env.example
├── .gitignore
├── package.json
└── README.md
```

## Frontend stack

- Expo SDK 56
- Expo Router
- React Native + React Native Web
- TypeScript strict mode
- TanStack Query
- React Hook Form
- Zod
- i18next

## Backend target

- Laravel 13
- PHP 8.4+
- MySQL
- Laravel Sanctum

## Verified commands

From the repository root:

```bash
npm run client:typecheck
npm run client:web
docker compose up --build -d
```

From `apps/client`:

```bash
npm run typecheck
npx expo export --platform web
```

From `apps/api` through Docker:

```bash
docker compose exec -T api php artisan test
docker compose exec -T api php artisan db:seed --force
```

## Environment variables

Root `.env.example`:

```env
EXPO_PUBLIC_API_URL=http://localhost:8000/api/v1
EXPO_PUBLIC_APP_ENV=development
EXPO_PUBLIC_ENABLE_LOCAL_AUTH_FALLBACK=true
```

`apps/api/.env.example`:

```env
APP_NAME=Humoo
APP_ENV=local
APP_KEY=
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:8081

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=humoo
DB_USERNAME=humoo
DB_PASSWORD=humoo

SANCTUM_STATEFUL_DOMAINS=localhost:8081,127.0.0.1:8081
SESSION_DOMAIN=localhost
```

## Current limitations

- The local Windows PHP is still `8.0.17`, so backend commands should run through Docker.
- The client still uses a local auth fallback until auth and organization endpoints are wired.
- The API currently exposes the foundational `GET /api/v1/health` endpoint plus schema and seed groundwork; auth and organization endpoints are the next backend slice.
