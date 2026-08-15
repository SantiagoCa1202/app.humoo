# ADR 0001: Monorepo base and environment blockers

## Status

Accepted on 2026-08-14

## Decision

- Keep a monorepo shape under `app.humoo`
- Implement the universal client first because it is executable today
- Reserve `apps/api` for Laravel 13 once PHP 8.3+ or Docker is available
- Use a local auth fallback only to validate route guards and shell flows

## Consequences

- Frontend development can continue immediately
- Backend integration remains blocked but clearly isolated
- No fake Laravel installation is committed under an incompatible PHP version
