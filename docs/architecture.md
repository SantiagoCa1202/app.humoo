# Architecture

## Current implementation

### `apps/client`

- Expo Router with route groups:
  - `(public)` for welcome, login, register, forgot password, reset password
  - `(onboarding)` for organization creation
  - `(app)` for the authenticated shell
- Feature screens live in `src/features/.../screens`
- Shared UI lives in `src/components`
- Session bootstrap uses `TanStack Query` plus platform-aware storage
- Theme and language preferences persist locally
- The API client and normalized error type are already defined

### `apps/api`

- Laravel 13 scaffolded under Docker
- API entrypoint at `/api/v1/health`
- Request ID middleware and normalized JSON success/error support
- Initial multitenant schema for users, organizations, memberships, roles,
  permissions, invitations, plans, features, and subscriptions
- Sanctum installed for the upcoming auth slice

## Client flow

1. `app/index.tsx` decides between public, onboarding, and private routes.
2. `AuthProvider` hydrates a persisted session.
3. `ThemeProvider` resolves system or manual theme preference.
4. `i18n` initializes from device locale and optional stored preference.

## Current tradeoff

The project still uses a local auth fallback inside the client because the real
auth endpoints are not implemented yet. This keeps route guards, onboarding,
shell, theme, and language executable while the backend foundation is built in
parallel.
