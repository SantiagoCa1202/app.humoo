# P0.4A - AI Efficiency

## Current state

- `AIOrchestrator` always uses the model-driven tool loop.
- Normal and clarification responses terminate directly with model text.
- Tool results share one canonical `data/signals/meta/error` envelope and carry
  their complete safe `result_ref_json` back to the model.
- Execution plans persist derived dependencies, bindings, confirmations,
  idempotency, progress, and resumable queue state.
- Structural validation errors are returned to the model as generally
  retryable and are bounded only by the global tool-loop limits.

## Target state

```text
user -> model -> hosted Tool Search -> authorized deferred tools
     -> ToolExecutor -> uniform observation -> model
     -> direct model response
```

- `ToolRegistry` remains the only capability catalog.
- A small control core is available immediately; authorized domain functions
  use Responses API `defer_loading` and are discovered by the model.
- Tool Search is enabled by default and provider failures never fall back to
  loading the complete catalog.
- Plans accept declarative result references in inputs and derive data
  dependencies deterministically without interpreting user language.
- Structural and argument repairs have explicit budgets and metrics.

## Expected files

- `apps/api/config/ai.php`, `.env.example`: provider and retry budgets.
- `ToolProfileSelector`, `ToolRegistry`, `OpenAiFunctionSchemaFactory`:
  authorized discovery profile, core/deferred metadata, provider schemas.
- `OpenAIProvider`, `AIOrchestrator`: hosted Tool Search transport,
  observations, direct-response contract, metrics, and retry enforcement.
- `ToolExecutor`: declarative execution-plan references and compatible
  terminal response validation.
- Focused unit, contract, and feature tests plus this comparison document.

## Compatibility risks

- Responses API/model availability for hosted Tool Search. An unsupported
  provider fails closed instead of silently expanding the prompt.
- Persistent provider conversations must receive every function output; Tool
  Search output items remain provider-owned and must not be replayed as Humoo
  function outputs.
- Capability filtering may expose incorrect permission metadata. Execution-time
  Gate/Policy checks remain authoritative and security tests must cover both
  discovery modes.

## Implemented contracts

### Discovery

- Immediate controls: `objectives.cancel`, `execution_plans.create`,
  `execution_plans.latest`, and `execution_plans.revise`.
- `tool_search` is the fifth initially loaded tool.
- Every authorized domain function remains in `ToolRegistry` and is sent with
  `defer_loading: true`. Detailed domain metadata is no longer duplicated in
  the developer instructions.
- Availability is filtered only by active membership and declared permission;
  semantic selection remains exclusively model-owned.
- Provider rejection of `tool_search` or `defer_loading` is surfaced as a
  provider failure. The runtime never loads all deferred tools as a fallback.

### Observations and termination

Every model-visible tool result has the same canonical fields: `ok`, `data`,
`error`, `signals`, and `meta`. Internal exception text is never included.

The model's `output_text` is the normal successful terminal response and
`tool_choice=auto` allows that response without a synthetic function call.
The model's normal response is the only terminal response path and is not
exposed as a synthetic tool. `objectives.cancel` is the explicit model
operation for cancelling the active objective, pending previews,
clarifications, and unexecuted plan steps; it never rolls back completed writes.
Confirmed tool outputs also return their complete safe `result_ref_json` when
the provider conversation resumes; they are not reduced to a small ID/status
summary.

An `AiObjective` is created lazily when the model first calls a write tool.
Conversational answers and reads keep only the `AiRun`. `AiObjectiveLifecycle`
is the single transition authority for pending, confirmed, executed, and
cancelled write work; confirmations and execution-plan rows are the durable
children it updates transactionally, not a second conversational state machine.

### Execution plans

Plans are valid only for at least two registered write steps. Reads are allowed
only as `completion_steps`. A model expresses a data dependency at the value
that consumes it:

```json
{
  "step_key": "add_item",
  "action_key": "menus.items.add",
  "after": [],
  "input": {
    "menu_id": {"$from": "create_menu.id"}
  }
}
```

Laravel converts this exact protocol marker to the durable internal
`depends_on_json` and `input_bindings_json` representation. `after` exists only
for sequencing without data transfer. Raw `depends_on` and `input_bindings`
inputs are rejected.

### Retry policy

- Structural plan validation: one repair.
- Ordinary tool arguments: one repair per action.
- Provider timeout/rate-limit/unavailable/invalid response: one retry.
- Permission failure: zero retries.
- Confirmation or missing user input: stop the active operation.
- An identical failed tool call is blocked before `ToolExecutor` runs again.

## Observability

- `ai.tool_discovery.enabled`, `.query`, `.result`, and `.failure` record the
  correlation/workspace, initial/deferred/discovered counts, observable search
  query, search count, and latency without chain-of-thought. Discovery failures
  fail closed; the runtime never retries with the complete catalog loaded.
- `ai.tool_loop.retry_budget` and `.retry_blocked` record bounded repair
  decisions.
- `ai.provider.transient_retry` separates provider retries.
- `ai.tool_loop.efficiency` is persisted alongside the `AiRun` metadata and
  records tokens, cache tokens, iterations, tool count, initial/deferred count,
  retry categories, first-attempt validity, and profile.

## P0.3 baseline and P0.4A verification snapshot

The incident run `01M1N5YZ0YCZNG4CDN0V8N9A0K` called
`execution_plans.create` three times. The first two calls failed on duplicated
completion binding structure; the third finally created the preview. P0.4A
removes those provider-authored mechanics and prevents a third executor call
after the single structural repair budget is exhausted.

Test/runtime instrumentation on 2026-09-04 observed:

| Metric | P0.3 | P0.4A |
| --- | ---: | ---: |
| Initially loaded tools | 107 | 5 |
| Authorized deferred domain tools in owner fixture | 0 | 82 |
| Structural repair budget | Global loop limit only | 1 |
| Identical failed call | Could execute again | Blocked before executor |
| Plan data-reference fields authored by model | 3 arrays/keys per binding | 1 `$from` value |

This is a 95.3% reduction in initially loaded tools for the tested owner
fixture. An apples-to-apples live token/latency comparison still requires an
authorized provider run with real workspace data; mocked provider tests do not
claim token savings. The runtime now records the required measurements when
that canary is executed.

## P0.4B boundary

This pass does not add SSE/WebSocket transport, durable progress-event
protocols, queue wake-up changes, reconnect/replay behavior, or frontend
activity rendering. Those remain P0.4B. P0.4A only emits backend discovery and
efficiency logs in addition to the existing chat activity events.
