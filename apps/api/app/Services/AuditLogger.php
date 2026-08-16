<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function logWorkspaceAction(
        Request $request,
        string $workspaceId,
        ?string $actorId,
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        return AuditLog::query()->create([
            'workspace_id' => $workspaceId,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before,
            'after_json' => $after,
            'source' => $this->detectSource($request),
            'correlation_id' => (string) $request->attributes->get('request_id'),
        ]);
    }

    private function detectSource(Request $request): string
    {
        $header = $request->header('X-Client-Source');

        if (in_array($header, ['web', 'mobile', 'api', 'ai', 'system'], true)) {
            return $header;
        }

        $userAgent = strtolower((string) $request->userAgent());

        if (
            str_contains($userAgent, 'android') ||
            str_contains($userAgent, 'iphone') ||
            str_contains($userAgent, 'expo') ||
            str_contains($userAgent, 'okhttp')
        ) {
            return 'mobile';
        }

        if (str_contains($userAgent, 'mozilla')) {
            return 'web';
        }

        return 'api';
    }
}
