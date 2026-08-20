<?php

namespace App\Application\Actions\Chat;

use App\AI\Presentation\ComponentRegistry;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Str;

class AssistantMessageWriter
{
    public function create(
        Conversation $conversation,
        Workspace $workspace,
        ?string $locale,
        array $payload,
        ?Message $parentMessage = null,
        array $metadata = []
    ): Message {
        $message = $this->createPending(
            $conversation,
            $workspace,
            $locale,
            $parentMessage,
            $metadata
        );

        return $this->complete(
            $message,
            $workspace,
            $payload,
            $locale,
            $metadata
        );
    }

    public function createPending(
        Conversation $conversation,
        Workspace $workspace,
        ?string $locale,
        ?Message $parentMessage = null,
        array $metadata = []
    ): Message {
        $timestamp = now();

        $message = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'sender_id' => null,
            'status' => 'pending',
            'locale' => $locale,
            'parent_message_id' => $parentMessage?->id,
            'metadata' => $this->normalizeMetadata($metadata),
        ]);

        $conversation->forceFill([
            'last_message_at' => $timestamp,
        ])->save();

        if ($parentMessage) {
            $parentMessage->forceFill([
                'updated_at' => $timestamp,
            ])->save();
        }

        return $message;
    }

    public function complete(
        Message $message,
        Workspace $workspace,
        array $payload,
        ?string $locale = null,
        array $metadata = []
    ): Message {
        $message->blocks()->delete();

        foreach ($payload['blocks'] ?? [] as $position => $block) {
            $this->createBlock($message, $workspace->id, $position, $block);
        }

        $message->forceFill([
            'content_text' => $this->firstTextBlock($payload['blocks'] ?? []),
            'error_code' => null,
            'error_message' => null,
            'locale' => $locale ?? $message->locale,
            'metadata' => $this->normalizeMetadata([
                ...$metadata,
                'suggestions' => $payload['suggestions'] ?? [],
            ]),
            'status' => 'completed',
        ])->save();

        return $message->fresh('blocks');
    }

    public function fail(
        Message $message,
        Workspace $workspace,
        string $errorCode,
        string $errorMessage,
        array $payload,
        ?string $locale = null,
        array $metadata = []
    ): Message {
        $message->blocks()->delete();

        foreach ($payload['blocks'] ?? [] as $position => $block) {
            $this->createBlock($message, $workspace->id, $position, $block);
        }

        $message->forceFill([
            'content_text' => $this->firstTextBlock($payload['blocks'] ?? []),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'locale' => $locale ?? $message->locale,
            'metadata' => $this->normalizeMetadata([
                ...$metadata,
                'suggestions' => $payload['suggestions'] ?? [],
            ]),
            'status' => 'failed',
        ])->save();

        return $message->fresh('blocks');
    }

    private function createBlock(
        Message $message,
        string $workspaceId,
        int $position,
        array $block
    ): void {
        $type = (string) ($block['type'] ?? 'text');

        if ($type === 'component') {
            $component = (string) ($block['component'] ?? '');
            $schemaVersion = (int) ($block['schema_version'] ?? 1);

            abort_unless(
                ComponentRegistry::supportsComponent($component, $schemaVersion),
                422,
                'Unsupported chat component.'
            );

            $message->blocks()->create([
                'workspace_id' => $workspaceId,
                'position' => $position,
                'block_type' => 'component',
                'component_key' => $component,
                'schema_version' => $schemaVersion,
                'instance_id' => (string) Str::ulid(),
                'payload_json' => [
                    'actions' => $block['actions'] ?? [],
                    'data' => $block['data'] ?? [],
                    'meta' => $block['meta'] ?? [],
                ],
                'refreshable' => (bool) ($block['meta']['refreshable'] ?? false),
                'generated_at' => now(),
            ]);

            return;
        }

        $message->blocks()->create([
            'workspace_id' => $workspaceId,
            'position' => $position,
            'block_type' => $type,
            'payload_json' => [
                'data' => $block['data'] ?? null,
                'meta' => $block['meta'] ?? [],
                'text' => $block['text'] ?? null,
            ],
            'generated_at' => now(),
        ]);
    }

    private function firstTextBlock(array $blocks): ?string
    {
        $firstTextBlock = collect($blocks)->first(
            fn (array $block) => ($block['type'] ?? null) === 'text'
                && is_string($block['text'] ?? null)
                && trim((string) $block['text']) !== ''
        );

        return $firstTextBlock['text'] ?? null;
    }

    private function normalizeMetadata(array $metadata): array
    {
        return array_filter(
            $metadata,
            fn ($value) => $value !== null && $value !== []
        );
    }
}
