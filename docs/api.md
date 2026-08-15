# API Plan

This repository now contains an executable Laravel 13 application in
`apps/api`.

## Implemented now

- `GET /api/v1/health`

## Planned next surface

- `GET /api/v1/health`
- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/forgot-password`
- `POST /api/v1/auth/reset-password`
- `GET /api/v1/auth/user`
- `GET /api/v1/organizations`
- `POST /api/v1/organizations`
- `POST /api/v1/organizations/{organization}/select`
- `GET /api/v1/profile`
- `PATCH /api/v1/profile`
- `PATCH /api/v1/profile/preferences`

The client already includes:

- `ApiError` normalization
- centralized request helper
- language header propagation
- organization context header support
- request timeout handling
