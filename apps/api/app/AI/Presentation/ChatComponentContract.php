<?php

namespace App\AI\Presentation;

final class ChatComponentContract
{
    public const VERSION = 1;

    /**
     * Adds the transport-level contract shared by every remote component.
     * Module payloads remain untouched apart from the shared contract fields.
     *
     * @param array<string, mixed> $block
     * @param array<string, mixed>|null $tool
     * @return array<string, mixed>
     */
    public static function normalizeBlock(array $block, ?array $tool = null): array
    {
        if (($block['type'] ?? null) !== 'component') {
            return $block;
        }

        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $meta = is_array($block['meta'] ?? null) ? $block['meta'] : [];
        $entityType = $meta['entity_type']
            ?? $data['entity_type']
            ?? ($tool['entity_type'] ?? null);
        $module = $meta['module']
            ?? $data['module']
            ?? ($tool['module'] ?? null);
        $operation = $meta['operation']
            ?? $data['operation']
            ?? ($tool['operation_type'] ?? null);
        $actionId = $meta['action_id']
            ?? ($tool['action_id'] ?? $tool['key'] ?? null);

        $contractMeta = array_filter([
            'contract_version' => $meta['contract_version'] ?? self::VERSION,
            'entity_type' => $entityType,
            'module' => $module,
            'operation' => $operation,
            'action_id' => $actionId,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $contractData = array_filter([
            'contract_version' => $data['contract_version'] ?? self::VERSION,
            'entity_type' => $data['entity_type'] ?? $entityType,
            'module' => $data['module'] ?? $module,
            'operation' => $data['operation'] ?? $operation,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            ...$block,
            'actions' => self::normalizeActions($block['actions'] ?? []),
            'data' => [...$data, ...$contractData],
            'meta' => [...$meta, ...$contractMeta],
            'schema_version' => (int) ($block['schema_version'] ?? self::VERSION),
        ];
    }

    /**
     * Normalizes every component block in a tool result while preserving the
     * result envelope used by the orchestrator and confirmation lifecycle.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $tool
     * @return array<string, mixed>
     */
    public static function normalizeResult(array $result, array $tool): array
    {
        if (!is_array($result['blocks'] ?? null)) {
            return $result;
        }

        $result['blocks'] = collect($result['blocks'])
            ->map(static fn (mixed $block): mixed => is_array($block)
                ? self::normalizeBlock($block, $tool)
                : $block)
            ->values()
            ->all();

        return $result;
    }

    /**
     * Enforces the stable action_id field while preserving action data.
     *
     * @param mixed $actions
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeActions(mixed $actions): array
    {
        if (!is_array($actions)) {
            return [];
        }

        return collect($actions)
            ->filter(static fn (mixed $action): bool => is_array($action))
            ->map(static function (array $action): array {
                $actionId = $action['action_id'] ?? $action['id'] ?? null;

                if ($actionId === null || $actionId === '') {
                    return $action;
                }

                return [
                    ...$action,
                    'action_id' => (string) $actionId,
                    'disabled' => (bool) ($action['disabled'] ?? false),
                    'requires_confirmation' => (bool) ($action['requires_confirmation'] ?? false),
                ];
            })
            ->values()
            ->all();
    }
}
