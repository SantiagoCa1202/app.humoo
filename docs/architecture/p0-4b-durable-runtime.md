# P0.4B — Durable Runtime

## Current flow and failure points

The chat POST already persists the user message and dispatches `ProcessChatMessage`, but the durable record is created later inside `AIOrchestrator`. That leaves the accepted request without an `ai_run_id`, an assistant placeholder, or canonical progress that a refreshed client can query. Progress is transported as ephemeral chat events, while execution-plan updates mutate an older message in place. A missed event can therefore leave the newest UI snapshot stale even though the database workflow completed.

`ProcessChatMessage` and `ExecuteAiExecutionPlan` already provide queue boundaries and overlap locks. Confirmations and execution-plan items are persisted and writes are routed through the canonical `ToolExecutor`. These owners are retained.

## Target flow

```text
POST /chat/messages
  -> persist user message + assistant placeholder + queued AI Run
  -> dispatch ProcessChatMessage(run_id)
  -> 202 Accepted

ProcessChatMessage
  -> acquire run/conversation lock
  -> mark AI Run running
  -> invoke the existing AIOrchestrator P0.4A tool loop
  -> persist waiting or terminal state
  -> publish versioned progress

Client
  -> render the persisted placeholder/run state
  -> realtime updates
  -> canonical polling while active
  -> reconcile on reconnect, focus, network recovery, and reload
```

The database is canonical. Realtime is only a low-latency transport. No semantic routing, parsing, or workflow planning is added to Laravel.

## Ownership boundaries

- `AiRun`: one durable model attempt for a user objective; present for normal and compound AI-first turns.
- `AiToolCall`: one model-selected registered tool invocation within a run.
- `AiExecutionPlan`: optional explicit durable multi-write workflow selected by the model.
- `ActionConfirmation`: persisted authorization boundary for a proposed write or plan.
- Conversation continuation state: persisted provider/tool output required to resume after user input or confirmation.

## Migration risks

- Existing `ai_runs.status=pending` records are migrated to `queued`; readers temporarily accept both values during rollout.
- Queue workers must be restarted after deployment so serialized jobs use the new run identifier.
- The migration adds nullable links first, preserving historical rows.
- Provider/semantic retry budget remains owned by P0.4A. Queue attempts are recorded separately as infrastructure retries.

## Files affected

- Laravel migrations/models/resources/runtime service, chat endpoint and queue jobs.
- Existing AI orchestrator integration points only; its model/tool selection loop remains unchanged.
- Existing chat API/types/hooks/realtime components for canonical run polling and reconciliation.
- Focused backend lifecycle/security/failure tests and client typechecking.
