<?php

namespace App\Application\Actions\Chat;

use App\Models\Conversation;
use App\Models\ConversationEntityRef;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class RecordConversationEntityRefs
{
    private const ROLES = ['active', 'recent', 'created', 'selected'];

    public function execute(
        Conversation $conversation,
        Workspace $workspace,
        array $references
    ): void {
        if ($conversation->workspace_id !== $workspace->id) {
            return;
        }

        DB::transaction(function () use ($conversation, $workspace, $references): void {
            foreach ($references as $reference) {
                if (!is_array($reference)) {
                    continue;
                }

                $type = trim((string) ($reference['type'] ?? ''));
                $entityId = trim((string) ($reference['id'] ?? ''));
                $role = trim((string) ($reference['role'] ?? 'recent'));

                if ($type === '' || $entityId === '' || !in_array($role, self::ROLES, true)) {
                    continue;
                }

                if ($role === 'active') {
                    ConversationEntityRef::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('conversation_id', $conversation->id)
                        ->where('entity_type', $type)
                        ->where('entity_id', $entityId)
                        ->where('role', 'recent')
                        ->delete();

                    ConversationEntityRef::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('conversation_id', $conversation->id)
                        ->where('entity_type', $type)
                        ->where('role', 'active')
                        ->where('entity_id', '!=', $entityId)
                        ->update(['role' => 'recent']);
                }

                ConversationEntityRef::query()->updateOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'entity_type' => $type,
                        'entity_id' => $entityId,
                        'role' => $role,
                    ],
                    [
                        'workspace_id' => $workspace->id,
                        'last_referenced_at' => now(),
                        'metadata_json' => $this->safeMetadata($reference),
                    ]
                );
            }
        });
    }

    private function safeMetadata(array $reference): array
    {
        $snapshot = is_array($reference['snapshot'] ?? null)
            ? $reference['snapshot']
            : [];

        return array_filter([
            'name' => is_string($snapshot['name'] ?? null) ? $snapshot['name'] : null,
            'section_name' => is_string($reference['section_name'] ?? null)
                ? $reference['section_name']
                : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
