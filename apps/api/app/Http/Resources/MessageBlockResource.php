<?php

namespace App\Http\Resources;

use App\AI\Presentation\ComponentRegistry;
use App\AI\Presentation\ChatComponentContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->payload_json) ? $this->payload_json : [];

        if ($this->block_type === 'component') {
            $component = (string) $this->component_key;
            $schemaVersion = (int) ($this->schema_version ?? 1);

            $componentBlock = ChatComponentContract::normalizeBlock([
                'actions' => $payload['actions'] ?? [],
                'component' => $component,
                'data' => $payload['data'] ?? [],
                'meta' => $payload['meta'] ?? [],
                'schema_version' => $schemaVersion,
                'type' => 'component',
            ]);

            return [
                'id' => $this->id,
                'type' => 'component',
                'component' => $component,
                'schema_version' => $schemaVersion,
                'registry_key' => ComponentRegistry::canonicalKey($component, $schemaVersion),
                'instance_id' => $this->instance_id,
                'data' => $componentBlock['data'],
                'actions' => $componentBlock['actions'],
                'meta' => [
                    'generated_at' => $this->generated_at?->toIso8601String(),
                    'refreshable' => (bool) $this->refreshable,
                    'stale_at' => $this->stale_at?->toIso8601String(),
                    ...((array) $componentBlock['meta']),
                ],
            ];
        }

        return [
            'id' => $this->id,
            'type' => $this->block_type,
            'text' => $payload['text'] ?? $this->message?->content_text,
            'data' => $payload['data'] ?? null,
            'meta' => [
                'generated_at' => $this->generated_at?->toIso8601String(),
                'refreshable' => (bool) $this->refreshable,
                'stale_at' => $this->stale_at?->toIso8601String(),
                ...((array) ($payload['meta'] ?? [])),
            ],
        ];
    }
}
