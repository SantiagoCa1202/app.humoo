<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Exceptions\AiProviderException;
use App\AI\Providers\OpenAIProvider;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class OpenAIHealthController
{
    public function __invoke(Request $request, OpenAIProvider $provider)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission($workspace->id, 'members.manage'),
            403,
            'You do not have permission to inspect AI provider health.'
        );

        try {
            return ApiResponse::success($provider->healthCheck(), [
                'request_id' => $request->attributes->get('request_id'),
            ]);
        } catch (AiProviderException $exception) {
            $status = in_array($exception->internalCode(), [
                'AI_AUTH_ERROR',
                'AI_BAD_REQUEST',
            ], true) ? 502 : 503;

            return ApiResponse::error(
                $request,
                'AI provider health check failed.',
                $exception->internalCode(),
                $status,
                [
                    'diagnostic' => $exception->metadata(),
                ]
            );
        }
    }
}
