<?php

namespace App\Application\Actions\Chat;

use App\Models\CapabilityRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordUnsupportedCapability
{
    public function execute(
        Workspace $workspace,
        ?User $user,
        ?Conversation $conversation,
        ?Message $message,
        array $payload
    ): CapabilityRequest {
        $this->validateWorkspaceContext($workspace, $user, $conversation, $message);

        $detectedIntent = $this->normalizeText($payload['detected_intent'] ?? null, 120);
        $module = $this->normalizeText($payload['module'] ?? null, 80);
        $requestedAction = $this->normalizeText($payload['requested_action'] ?? null, 180);

        if ($detectedIntent === '' || $requestedAction === '') {
            throw ValidationException::withMessages([
                'capability' => ['A clear unsupported capability is required.'],
            ]);
        }

        $normalizedKey = $this->normalizeKey(
            $payload['normalized_key'] ?? null,
            $module,
            $detectedIntent,
            $requestedAction
        );
        $module = $module !== ''
            ? $module
            : $this->moduleFromKey($normalizedKey);
        $metadata = $this->safeMetadata($payload['metadata'] ?? null);
        $now = now();

        return DB::transaction(function () use (
            $conversation,
            $message,
            $metadata,
            $detectedIntent,
            $module,
            $normalizedKey,
            $now,
            $requestedAction,
            $user,
            $workspace
        ): CapabilityRequest {
            $query = CapabilityRequest::query()
                ->where('workspace_id', $workspace->id)
                ->where('normalized_key', $normalizedKey)
                ->lockForUpdate();
            $existing = $query->first();

            if ($existing && $message && $existing->message_id === $message->id) {
                return $existing;
            }

            if ($existing) {
                $existing->forceFill([
                    'last_requested_at' => $now,
                    'metadata_json' => $metadata !== [] ? $metadata : $existing->metadata_json,
                    'occurrences' => $existing->occurrences + 1,
                ])->save();

                return $existing->fresh();
            }

            return CapabilityRequest::query()->create([
                'conversation_id' => $conversation?->id,
                'detected_intent' => $detectedIntent,
                'first_requested_at' => $now,
                'last_requested_at' => $now,
                'message_id' => $message?->id,
                'metadata_json' => $metadata !== [] ? $metadata : null,
                'module' => $module !== '' ? $module : null,
                'normalized_key' => $normalizedKey,
                'occurrences' => 1,
                'requested_action' => $requestedAction,
                'status' => 'unsupported',
                'user_id' => $user?->id,
                'workspace_id' => $workspace->id,
            ]);
        });
    }

    private function validateWorkspaceContext(
        Workspace $workspace,
        ?User $user,
        ?Conversation $conversation,
        ?Message $message
    ): void {
        if ($user && !$user->memberships()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages([
                'workspace_id' => ['The user does not belong to the active workspace.'],
            ]);
        }

        if ($conversation && $conversation->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages([
                'conversation_id' => ['The conversation does not belong to the active workspace.'],
            ]);
        }

        if ($message && (
            $message->workspace_id !== $workspace->id
            || ($conversation && $message->conversation_id !== $conversation->id)
        )) {
            throw ValidationException::withMessages([
                'message_id' => ['The message does not belong to the active conversation workspace.'],
            ]);
        }
    }

    private function normalizeKey(
        mixed $provided,
        string $module,
        string $detectedIntent,
        string $requestedAction
    ): string {
        $semanticText = Str::lower($detectedIntent.' '.$requestedAction);

        if (
            Str::contains($semanticText, ['prep', 'preparacion', 'preparación'])
            && Str::contains($semanticText, ['supplier', 'proveedor'])
            && Str::contains($semanticText, ['send', 'enviar', 'manda', 'mandar', 'envia', 'envi'])
        ) {
            return 'purchasing.send_prep_to_supplier';
        }

        $candidate = trim((string) ($provided ?: $module.'.'.$detectedIntent), " .\t\n\r\0\x0B");
        $segments = preg_split('/[.\s_-]+/', Str::ascii($candidate)) ?: [];
        $segments = collect($segments)
            ->map(fn (string $segment): string => Str::slug($segment, '_'))
            ->filter()
            ->values();

        return $segments->count() > 1
            ? $segments->implode('.')
            : ($segments->first() ?: 'unknown.unsupported_capability');
    }

    private function moduleFromKey(string $normalizedKey): string
    {
        return (string) Str::before($normalizedKey, '.');
    }

    private function normalizeText(mixed $value, int $limit): string
    {
        return Str::limit((string) Str::of((string) $value)
            ->ascii()
            ->lower()
            ->squish(), $limit, '');
    }

    private function safeMetadata(mixed $metadata): array
    {
        if (!is_array($metadata)) {
            return [];
        }

        $safe = [];
        foreach (['confidence', 'model_key', 'provider'] as $key) {
            $value = $metadata[$key] ?? null;

            if (is_string($value) || is_numeric($value)) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
