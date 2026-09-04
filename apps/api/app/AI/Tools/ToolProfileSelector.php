<?php

namespace App\AI\Tools;

use App\Models\WorkspaceMembership;

final class ToolProfileSelector
{
    /** @var array<int, string> */
    private const CORE_KEYS = [
        'orchestration.respond',
        'execution_plans.create',
        'execution_plans.latest',
        'execution_plans.revise',
    ];

    /**
     * Keep the complete runtime registry available to the model.
     *
     * Intent, module selection, entity resolution, dependencies, and turn
     * sequencing belong to the model. This compatibility boundary therefore
     * does not classify text locally with keywords, regular expressions, or a
     * competing parser.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $metadata
     * @return array{
     *   profile:string,
     *   metadata:array<int, array<string, mixed>>,
     *   prompt_metadata:array<int, array<string, mixed>>,
     *   fallback_metadata:array<int, array<string, mixed>>,
     *   discovery_enabled:bool,
     *   core_count:int,
     *   initial_tool_count:int,
     *   deferred_count:int
     * }
     */
    public function select(array $context, array $metadata): array
    {
        if (! (bool) config('ai.tool_discovery.enabled', false)) {
            return [
                'profile' => 'all',
                'metadata' => $metadata,
                'prompt_metadata' => $metadata,
                'fallback_metadata' => $metadata,
                'discovery_enabled' => false,
                'core_count' => count($metadata),
                'initial_tool_count' => count($metadata),
                'deferred_count' => 0,
            ];
        }

        $authorized = $this->authorizedMetadata($context, $metadata);
        $profiled = collect($authorized)
            ->map(fn (array $tool): array => [
                ...$tool,
                'defer_loading' => ! in_array((string) ($tool['key'] ?? ''), self::CORE_KEYS, true),
            ])
            ->values()
            ->all();
        $core = collect($profiled)
            ->reject(fn (array $tool): bool => (bool) ($tool['defer_loading'] ?? false))
            ->values()
            ->all();

        return [
            'profile' => 'discovery',
            'metadata' => $profiled,
            // Detailed domain contracts are carried by deferred function
            // definitions. Repeating them in instructions defeats discovery.
            'prompt_metadata' => $core,
            'fallback_metadata' => collect($authorized)
                ->map(fn (array $tool): array => [...$tool, 'defer_loading' => false])
                ->values()
                ->all(),
            'discovery_enabled' => true,
            'core_count' => count($core),
            'initial_tool_count' => count($core) + 1,
            'deferred_count' => count($profiled) - count($core),
        ];
    }

    /** @return array<int, string> */
    public function coreKeys(): array
    {
        return self::CORE_KEYS;
    }

    /**
     * Availability filtering is authorization, not semantic routing. The
     * executor's Gate/Policy checks remain authoritative for every call.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $metadata
     * @return array<int, array<string, mixed>>
     */
    private function authorizedMetadata(array $context, array $metadata): array
    {
        $membership = $context['membership'] ?? null;
        if (! $membership instanceof WorkspaceMembership) {
            return [];
        }

        $membership->loadMissing('role.permissions');
        $permissionKeys = $membership->role?->permissions
            ? $membership->role->permissions->pluck('key')->filter()->all()
            : [];

        return collect($metadata)
            ->filter(function (array $tool) use ($permissionKeys): bool {
                $key = (string) ($tool['key'] ?? '');

                // These capabilities only control the AI turn or wrap steps
                // that are individually authorized by ToolExecutor. Active
                // workspace membership is their availability boundary.
                return in_array($key, self::CORE_KEYS, true)
                    || in_array((string) ($tool['permission'] ?? ''), $permissionKeys, true);
            })
            ->values()
            ->all();
    }
}
