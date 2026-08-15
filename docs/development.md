# Development

## Prerequisites

- Node `20.19.0`
- npm `10.8.2`
- PHP `8.0.17`
- Composer `2.6.2`

## Verified frontend workflow

```bash
cd apps/client
npm run typecheck
npx expo export --platform web
```

## Running the frontend

From the repo root:

```bash
npm run client:web
```

## Backend execution

Laravel 13 is scaffolded in `apps/api`, but the local Windows PHP is still
`8.0.17`. Run backend commands through Docker instead of the host PHP.

```bash
docker compose up --build -d
docker compose exec -T api php artisan test
docker compose exec -T api php artisan db:seed --force
```

## Recommended next environment step

1. Keep Docker as the backend runtime until local PHP is upgraded to `8.4+`.
2. Implement auth endpoints with Sanctum.
3. Replace the local auth fallback with real API mutations.
