# Humoo V1 functional acceptance checklist

Prompt 68 status ledger. This document is intentionally reusable for Prompt 69.
`PASS` means the check was executed successfully. `BLOCKED` means the check was
not executed because the required local service or fixture was unavailable.
Compilation alone never changes a check to `PASS`.

## Run context

- Date: 2026-08-23
- Repository: `app.humoo`
- Acceptance data: the automated core/BEO tests create realistic records through
  HTTP endpoints; they do not seed the primary workspace entities directly.
- Golden PDFs: unavailable in the repository and local document search, so no
  golden-document claim is made here.
- Runtime blocker: local PHP is 8.0.17 while `apps/api/composer.json` requires
  PHP `^8.4.1`; no API/worker/database services were listening during this pass.

## Acceptance matrix

| Area | Status | Evidence / next execution |
| --- | --- | --- |
| Auth register/login/logout/protected API | BLOCKED | `AuthApiTest`; run when Laravel boots on PHP 8.4+. |
| Workspace creation/context/membership | BLOCKED | `WorkspaceApiTest`, `FunctionalAcceptanceCoreWorkflowTest`. |
| Tenant isolation / direct IDs | BLOCKED | `TenantIsolationTest`, core acceptance tenant test. |
| Clients / contacts | BLOCKED | `DirectoryApiTest`, core acceptance workflow. |
| Property | NOT WIRED | Model/resource exists for BEO, but no normal Property API, policy, permission, or client screen exists. |
| Venues | BLOCKED | `DirectoryApiTest`, core acceptance workflow; property linking remains unverified. |
| Events and optimistic locking | BLOCKED | `EventApiTest`, core acceptance workflow. |
| Recipes / menu / event-menu relation | BLOCKED | `RecipeApiTest`, `MenuApiTest`, core acceptance workflow. |
| Prep generation / assignment / regeneration | BLOCKED | `GeneratePrepListTest`, `PrepItemOptimisticLockTest`, core acceptance workflow. |
| Tasks / team / command center | BLOCKED | `TeamStaffApiTest`, `CommandCenterApiTest`, core acceptance workflow. |
| Global search | BLOCKED | Search endpoint is wired; execute from the created workspace. |
| Notifications / realtime | BLOCKED | Existing targeted tests and service wiring require the Laravel runtime. |
| BEO upload/private extraction worker | BLOCKED | `DocumentApiTest` and worker tests exist; no running API/worker and no representative PDF available. |
| BEO review/revision canonical safety | BLOCKED | `FunctionalAcceptanceBeoWorkflowTest` and `BeoDomainApiTest`; execute after runtime restore. |
| BEO operational visibility | BLOCKED | `BeoDomainApiTest`; execute with hidden-function fixture. |
| AI read tools | PARTIAL | Rule-based orchestration and allowlist exist; live conversation/tool result is unverified. |
| AI write confirmation/permissions | PARTIAL | Server confirmation and permission paths exist for prep/task updates; client/event creation tools are not registered. |
| AI cross-tenant safety | BLOCKED | Static allowlist test added; feature execution requires API runtime. |
| Error recovery / stale confirmation | BLOCKED | Existing client/backend paths are present; execute targeted feature tests and UI smoke. |
| ES/EN and light/dark UI smoke | BLOCKED | Client typecheck is available; device/browser smoke was not available in this pass. |

## Automated acceptance groups

Added for this prompt:

- `FunctionalAcceptanceCoreWorkflowTest`: register, create workspace, create
  client/contact/venue/event, create recipes/menu, generate and regenerate prep,
  assign task, inspect command center/search, and reject a foreign event ID.
- `FunctionalAcceptanceBeoWorkflowTest`: import a representative revision through
  the BEO API and verify the canonical event is unchanged before review/apply.
- `AiAcceptanceSecurityTest`: allowlist and confirmation-gating invariants.

Run the focused groups after PHP 8.4+ and the test database are available:

```powershell
php artisan test tests/Feature/Feature/FunctionalAcceptanceCoreWorkflowTest.php tests/Feature/Feature/FunctionalAcceptanceBeoWorkflowTest.php tests/Unit/Unit/AiAcceptanceSecurityTest.php
```

Do not mark the release gates below `PASS` until the real API and, where
applicable, client/worker flows have been executed.

## Release gates

`AUTH`, `WORKSPACE`, `MULTITENANT`, `CLIENT`, `EVENT`, `RECIPES`, `MENU`,
`PREP`, `TASKS`, `COMMAND CENTER`, `BEO UPLOAD`, `BEO EXTRACTION`, `BEO REVIEW`,
`BEO REVISION SAFETY`, `AI READ`, `AI WRITE CONFIRMATION`, `AI PERMISSIONS`,
`AI CROSS-TENANT`, and `ERROR RECOVERY`: **BLOCKED pending runtime validation**.

`PROPERTY`: **NOT WIRED** pending a normal scoped API/UI path. This is a known
V1 integration gap, not a successful acceptance result.

Recommendation: **NOT READY FOR PROMPT 69**.
