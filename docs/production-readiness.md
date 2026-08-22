# Production Readiness

This document is a release gate for Humoo V1. It prepares configuration and
operational checks; it does not deploy infrastructure, create cloud resources,
or submit mobile builds.

## Environment separation

Maintain separate values and credentials for `development`, `staging`, and
`production`. Only `.env.example` files are versioned. Never copy a local
`.env` into a release image or commit provider credentials.

The current `docker compose.yml` is a local development stack. It uses a
bind-mounted source tree, the Laravel CLI server, and development MySQL
credentials. It must not be used as the production runtime.

## API release gate

Required production values:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, and explicit
  `FRONTEND_URLS` values using HTTPS.
- MySQL `DB_*` credentials from the secret manager. Run migrations as a
  reviewed release step, not automatically from the application startup.
- `SESSION_SECURE_COOKIE=true`, an intentional `SESSION_DOMAIN`, and
  `SANCTUM_STATEFUL_DOMAINS` containing only trusted browser origins.
- `FILESYSTEM_DISK=s3` plus the S3 credentials/bucket, or an equivalent
  private object-storage adapter. Documents must not be public.
- A transactional `MAIL_MAILER` and verified `MAIL_FROM_ADDRESS`.
- `QUEUE_CONNECTION` backed by a durable worker and a configured failed-job
  alert path. The current V1 code has no scheduled business commands.
- `BROADCAST_CONNECTION=pusher` (or the selected compatible provider) and all
  `PUSHER_*` server credentials. Realtime channel authorization remains
  bearer-token and workspace scoped.
- `AI_PROVIDER=rule_based` until an approved server-side provider adapter and
  its secret are available. No AI secret belongs in Expo variables.
- `BILLING_PROVIDER` and `BILLING_WEBHOOK_SECRET` only when the provider,
  signature verification, retry policy, and reconciliation process are ready.

Before release, run from the API runtime:

```bash
php artisan about
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate:status
php artisan migrate --force
php artisan queue:restart
```

Run the worker independently from the HTTP process. A typical supervised
command is:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

If future scheduled commands are added, run one scheduler process or a cron
entry for `php artisan schedule:run`; do not run duplicate schedulers.

## Health, logs, backup, rollback

- Liveness: `GET /up`.
- Readiness: `GET /api/v1/health`; it returns `503` when MySQL is unavailable.
- Log to stderr or a managed daily channel at `info` or higher. Never use
  `debug` in production and never log tokens, passwords, document contents, or
  provider responses containing sensitive data.
- Back up MySQL before migrations and retain an encrypted, restorable backup.
  Test restore in staging before the first production release.
- Store Laravel `APP_KEY`, database credentials, provider secrets, and object
  storage credentials in a secret manager. Rotation requires an explicit
  rollback plan; do not generate a new `APP_KEY` during startup.
- Roll back application code first, then use a reviewed migration rollback or
  forward-fix with the backup available. No automatic destructive rollback is
  configured.

## Client and EAS

`apps/client/eas.json` separates internal development, staging, and production
channels. Set the following in protected EAS environments, not in the repo:

- `EXPO_PUBLIC_API_URL` and optionally `EXPO_PUBLIC_API_PATH_PREFIX`.
- `EXPO_PUBLIC_REALTIME_URL`, `EXPO_PUBLIC_REALTIME_KEY`, and
  `EXPO_PUBLIC_REALTIME_AUTH_URL` for the selected production realtime service.
- `EXPO_PUBLIC_IOS_BUNDLE_IDENTIFIER` and `EXPO_PUBLIC_ANDROID_PACKAGE` to the
  identifiers registered in Apple and Google consoles.
- `EXPO_PUBLIC_ENABLE_LOCAL_AUTH_FALLBACK=false` for every non-development
  build.

The `humoo` scheme is already configured for native deep links and the web
reset-password route is present. Verify universal/app links, store signing,
push credentials, and the registered production identifiers on real devices
before release; those external registrations are not stored here.

## Release checks

Do not make real AI, billing, push, or email calls in CI. The repository CI
typechecks/exports the client and runs API readiness tests with test-safe
providers. The local project preference is to leave the test suite available
but not execute it during this production-preparation phase.

The remaining release blockers are external: production domains, secret-store
values, managed MySQL/object storage, mail/realtime/AI/billing providers,
observability and alert destinations, worker supervision, and Apple/Google
signing plus link registration.
