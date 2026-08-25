<?php

namespace App\AI\Intent;

use App\AI\Tools\ToolRegistry;
use App\Models\IntentPattern;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IntentPatternRegistry
{
    public function __construct(private ToolRegistry $toolRegistry)
    {
    }

    public function match(string $workspaceId, string $message): ?array
    {
        $normalizedMessage = $this->normalize($message);
        if ($normalizedMessage === '') {
            return null;
        }

        $patterns = IntentPattern::query()
            ->where('status', 'active')
            ->where(function ($query) use ($workspaceId): void {
                $query->where('workspace_id', $workspaceId)
                    ->orWhere(function ($global) {
                        $global->whereNull('workspace_id')
                            ->where('scope', 'global');
                    });
            })
            ->orderByRaw('CASE WHEN workspace_id = ? THEN 0 ELSE 1 END', [$workspaceId])
            ->orderByDesc('confidence')
            ->orderByDesc('successful_executions')
            ->limit(25)
            ->get();

        foreach ($patterns as $pattern) {
            $definition = is_array($pattern->pattern_json) ? $pattern->pattern_json : [];
            if (!$this->matchesDefinition($normalizedMessage, $definition)) {
                continue;
            }

            $actionKey = (string) $pattern->action_key;
            $intent = $this->intentForAction($actionKey);
            if ($intent === null) {
                continue;
            }

            return [
                'intent' => $intent,
                'model' => 'humoo-hybrid-router',
                'provider' => 'hybrid_router',
                'usage' => [
                    'completion_tokens' => 0,
                    'prompt_tokens' => 0,
                    'total_tokens' => 0,
                ],
                'slots' => $this->safeSlots($definition),
                'routing' => [
                    'action_key' => $actionKey,
                    'confidence' => (float) $pattern->confidence,
                    'matched_pattern_id' => $pattern->id,
                    'source' => 'learned',
                ],
            ];
        }

        return null;
    }

    public function observe(
        string $workspaceId,
        array $decision,
        bool $successfulExecution
    ): ?IntentPattern {
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];
        if (($routing['source'] ?? null) !== 'ai' || !$successfulExecution) {
            return null;
        }

        $actionKey = (string) ($routing['action_key'] ?? '');
        if ($actionKey === '' || $this->toolRegistry->actionKeyForIntent($actionKey) === null) {
            return null;
        }

        $tool = $this->toolRegistry->resolve($actionKey);
        $now = now();
        $confidence = $this->normalizeConfidence(
            $routing['confidence']
                ?? (is_array($decision['slots'] ?? null) ? ($decision['slots']['confidence'] ?? null) : null)
        );
        $definition = $this->definitionFor($actionKey);
        $slotSchema = $this->slotSchema($decision);

        return DB::transaction(function () use (
            $actionKey,
            $confidence,
            $definition,
            $now,
            $slotSchema,
            $tool,
            $workspaceId
        ): IntentPattern {
            $pattern = IntentPattern::query()
                ->where('workspace_id', $workspaceId)
                ->where('action_key', $actionKey)
                ->where('normalized_key', $actionKey)
                ->lockForUpdate()
                ->first();

            if (!$pattern) {
                return IntentPattern::query()->create([
                    'action_key' => $actionKey,
                    'ambiguity_rate' => $confidence < $this->minimumConfidence() ? 1 : 0,
                    'confidence' => $confidence,
                    'examples' => [[
                        'action_key' => $actionKey,
                        'slot_schema' => $slotSchema,
                    ]],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'normalized_key' => $actionKey,
                    'occurrences' => 1,
                    'pattern_json' => $definition,
                    'router_version' => (string) config('ai.hybrid_router_version', 'hybrid-router-v1'),
                    'scope' => 'workspace',
                    'slot_schema' => $slotSchema,
                    'status' => 'observed',
                    'successful_executions' => 1,
                    'workspace_id' => $workspaceId,
                ]);
            }

            $occurrences = $pattern->occurrences + 1;
            $successfulExecutions = $pattern->successful_executions + 1;
            $successRate = $successfulExecutions / max(1, $occurrences);
            $confidence = round(((float) $pattern->confidence * $pattern->occurrences + $confidence) / $occurrences, 6);
            $ambiguityRate = $confidence < $this->minimumConfidence()
                ? min(1, ((float) $pattern->ambiguity_rate * $pattern->occurrences + 1) / $occurrences)
                : (float) $pattern->ambiguity_rate;

            $pattern->forceFill([
                'ambiguity_rate' => $ambiguityRate,
                'confidence' => $confidence,
                'examples' => $this->appendExample($pattern->examples, $slotSchema, $actionKey),
                'last_seen_at' => $now,
                'occurrences' => $occurrences,
                'slot_schema' => $slotSchema,
                'status' => $this->nextStatus(
                    (string) $pattern->status,
                    $occurrences,
                    $successRate,
                    $confidence,
                    $ambiguityRate
                ),
                'successful_executions' => $successfulExecutions,
            ])->save();

            return $pattern->fresh();
        });
    }

    public function recordFailure(string $workspaceId, array $decision): ?IntentPattern
    {
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];
        if (($routing['source'] ?? null) !== 'ai') {
            return null;
        }

        $actionKey = (string) ($routing['action_key'] ?? '');
        if ($actionKey === '' || $this->toolRegistry->actionKeyForIntent($actionKey) === null) {
            return null;
        }

        $now = now();

        return DB::transaction(function () use ($actionKey, $decision, $now, $workspaceId): ?IntentPattern {
            $pattern = IntentPattern::query()
                ->where('workspace_id', $workspaceId)
                ->where('action_key', $actionKey)
                ->where('normalized_key', $actionKey)
                ->lockForUpdate()
                ->first();

            if (!$pattern) {
                return IntentPattern::query()->create([
                    'action_key' => $actionKey,
                    'ambiguity_rate' => 1,
                    'confidence' => $this->normalizeConfidence($decision['routing']['confidence'] ?? null),
                    'examples' => [],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'normalized_key' => $actionKey,
                    'occurrences' => 1,
                    'pattern_json' => $this->definitionFor($actionKey),
                    'router_version' => (string) config('ai.hybrid_router_version', 'hybrid-router-v1'),
                    'scope' => 'workspace',
                    'slot_schema' => [],
                    'status' => 'observed',
                    'successful_executions' => 0,
                    'failed_executions' => 1,
                    'workspace_id' => $workspaceId,
                ]);
            }

            $occurrences = $pattern->occurrences + 1;
            $failedExecutions = $pattern->failed_executions + 1;
            $pattern->forceFill([
                'ambiguity_rate' => min(1, $failedExecutions / max(1, $occurrences)),
                'failed_executions' => $failedExecutions,
                'last_seen_at' => $now,
                'occurrences' => $occurrences,
                'status' => in_array($pattern->status, ['active', 'validated'], true)
                    ? 'candidate'
                    : $pattern->status,
            ])->save();

            return $pattern->fresh();
        });
    }

    public function definitionFor(string $actionKey): array
    {
        return [
            'action_key' => $actionKey,
            'required_terms' => $this->requiredTerms($actionKey),
            'slot_schema' => [],
            'slots' => ['action_key' => $actionKey],
        ];
    }

    private function matchesDefinition(string $message, array $definition): bool
    {
        $requiredTerms = $definition['required_terms'] ?? [];
        if (!is_array($requiredTerms) || $requiredTerms === []) {
            return false;
        }

        foreach ($requiredTerms as $termGroup) {
            $terms = is_array($termGroup) ? $termGroup : [$termGroup];
            $matched = collect($terms)->contains(
                fn (mixed $term): bool => is_string($term) && Str::contains($message, $this->normalize($term))
            );

            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private function safeSlots(array $definition): array
    {
        $slots = $definition['slots'] ?? [];

        return is_array($slots) ? $slots : [];
    }

    private function intentForAction(string $actionKey): ?string
    {
        foreach ([
            'show_events',
            'show_my_tasks',
            'show_prep_lists',
            'update_prep_item',
            'update_task',
            'create_task',
            'create_menu',
            'search_menus',
            'show_menu',
            'rename_menu',
            'add_menu_item',
            'move_menu_item_section',
        ] as $intent) {
            if ($this->toolRegistry->actionKeyForIntent($intent) === $actionKey) {
                return $intent;
            }
        }

        return $this->toolRegistry->actionKeyForIntent($actionKey) !== null
            ? 'tool_action'
            : null;
    }

    private function nextStatus(
        string $status,
        int $occurrences,
        float $successRate,
        float $confidence,
        float $ambiguityRate
    ): string {
        if (in_array($status, ['disabled', 'rejected'], true)) {
            return $status;
        }

        if (
            $occurrences >= (int) config('ai.pattern_learning.active_occurrences', 5)
            && $successRate >= (float) config('ai.pattern_learning.active_success_rate', 0.95)
            && $confidence >= $this->minimumConfidence()
            && $ambiguityRate <= (float) config('ai.pattern_learning.maximum_ambiguity_rate', 0.10)
        ) {
            return 'active';
        }

        if (
            $occurrences >= (int) config('ai.pattern_learning.validated_occurrences', 3)
            && $successRate >= 0.90
            && $confidence >= $this->minimumConfidence()
            && $ambiguityRate <= (float) config('ai.pattern_learning.maximum_ambiguity_rate', 0.10)
        ) {
            return 'validated';
        }

        if ($occurrences >= (int) config('ai.pattern_learning.candidate_occurrences', 2)) {
            return 'candidate';
        }

        return 'observed';
    }

    private function requiredTerms(string $actionKey): array
    {
        if (preg_match('/^menus\.(update|items\.(update|delete))$/', $actionKey, $matches) === 1) {
            return [
                ['menu', 'menus', 'menÃº', 'menú'],
                $actionKey === 'menus.update' || $matches[2] === 'update'
                    ? ['update', 'change', 'modify', 'actualiza', 'cambia', 'edita']
                    : ['delete', 'remove', 'elimina', 'borra'],
            ];
        }

        if (preg_match('/^recipes\.(list|detail|create|update|scale|versions)$/', $actionKey, $matches) === 1) {
            $terms = match ($matches[1]) {
                'list', 'detail' => ['show', 'list', 'view', 'muestra', 'mostrar', 'ver', 'buscar', 'search'],
                'create' => ['create', 'add', 'new', 'crea', 'crear', 'agrega'],
                'update' => ['update', 'change', 'modify', 'actualiza', 'cambia', 'edita'],
                'scale' => ['scale', 'escalar', 'escala', 'multiply', 'multiplica'],
                default => ['version', 'versions', 'versiones', 'history', 'historial'],
            };
            return [$terms, ['recipe', 'recipes', 'receta', 'recetas']];
        }

        if (preg_match('/^(events|clients|contacts|venues)\.(list|detail|create|update|cancel|delete)$/', $actionKey, $matches) === 1) {
            $terms = match ($matches[2]) {
                'list', 'detail' => ['show', 'list', 'view', 'display', 'muestra', 'mostrar', 'ver', 'lista', 'listar'],
                'create' => ['create', 'add', 'new', 'crea', 'crear', 'agrega', 'nuevo', 'nueva'],
                'update' => ['update', 'change', 'modify', 'actualiza', 'cambia', 'modifica', 'mueve', 'set'],
                'cancel' => ['cancel', 'cancela', 'cancelar'],
                default => ['delete', 'remove', 'elimina', 'borrar', 'borra'],
            };
            $noun = match ($matches[1]) {
                'events' => ['event', 'events', 'evento', 'eventos'],
                'clients' => ['client', 'clients', 'cliente', 'clientes'],
                'contacts' => ['contact', 'contacts', 'contacto', 'contactos'],
                default => ['venue', 'venues', 'lugar', 'lugares', 'location', 'locations'],
            };
            return [$terms, $noun];
        }

        return match ($actionKey) {
            'events.list' => [['event', 'events', 'evento', 'eventos']],
            'prep.list' => [['prep', 'production', 'produccion', 'mise en place']],
            'prep.detail' => [['prep', 'production', 'produccion', 'mise en place'], ['show', 'view', 'display', 'muestra', 'mostrar', 'ver']],
            'prep.items.list', 'prep.items.detail' => [['prep', 'production', 'produccion', 'mise en place'], ['item', 'items', 'ítem', 'ítems']],
            'prep.generate' => [['prep', 'production', 'produccion', 'mise en place'], ['generate', 'create', 'build', 'make', 'genera', 'generar', 'crear', 'produce']],
            'prep.regenerate' => [['prep', 'production', 'produccion', 'mise en place'], ['regenerate', 'recalculate', 'regenera', 'regenerar', 'recalcular']],
            'prep.update', 'prep.items.update' => [['prep', 'production', 'produccion', 'mise en place'], ['update', 'change', 'actualiza', 'cambia', 'set']],
            'prep.items.complete' => [['prep', 'production', 'produccion', 'mise en place'], ['complete', 'done', 'mark', 'completa', 'termina', 'marca']],
            'prep.items.reopen' => [['prep', 'production', 'produccion', 'mise en place'], ['reopen', 'reabrir', 'reabre']],
            'prep.items.assign', 'prep.items.unassign' => [['prep', 'production', 'produccion', 'mise en place'], ['assign', 'unassign', 'asigna', 'quita asignacion']],
            'tasks.mine' => [['task', 'tasks', 'tarea', 'tareas']],
            'menus.search' => [['search', 'find', 'buscar', 'busca'], ['menu', 'menus', 'menú', 'menús']],
            'menus.show' => [['show', 'display', 'view', 'muestra', 'muéstrame', 'ver'], ['menu', 'menus', 'menú', 'menús']],
            'menus.rename' => [['rename', 'renombrar', 'cambiar nombre'], ['menu', 'menus', 'menú', 'menús']],
            'menus.items.add' => [['add', 'agrega', 'añade', 'agregar'], ['item', 'dish', 'ítem', 'plato']],
            'menus.items.move_section' => [['move', 'mueve', 'mover'], ['section', 'seccion', 'sección']],
            'prep_items.update' => [['update', 'complete', 'mark', 'actualiza', 'completa', 'marca'], ['prep', 'item', 'ítem']],
            'tasks.update' => [['update', 'change', 'assign', 'actualiza', 'cambia', 'asigna'], ['task', 'tarea']],
            'tasks.create' => [['create', 'add', 'new', 'crea', 'crear', 'agrega', 'nueva'], ['task', 'tarea']],
            'menus.create' => [['create', 'build', 'make', 'crea', 'crear'], ['menu', 'menus', 'menú', 'menús']],
            default => [],
        };
    }

    private function slotSchema(array $decision): array
    {
        $slots = $decision['slots'] ?? [];
        if (!is_array($slots)) {
            return [];
        }

        return collect(array_keys($slots))
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== 'menu_draft')
            ->values()
            ->all();
    }

    private function appendExample(mixed $examples, array $slotSchema, string $actionKey): array
    {
        $examples = is_array($examples) ? $examples : [];
        $examples[] = [
            'action_key' => $actionKey,
            'slot_schema' => $slotSchema,
        ];

        return array_slice($examples, -5);
    }

    private function normalizeConfidence(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.80;
        }

        return round(min(1, max(0, (float) $value)), 6);
    }

    private function minimumConfidence(): float
    {
        return (float) config('ai.pattern_learning.minimum_confidence', 0.90);
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(Str::squish($value)));
    }
}
