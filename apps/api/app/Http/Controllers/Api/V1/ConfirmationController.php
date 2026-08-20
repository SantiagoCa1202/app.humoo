<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionConfirmation;
use Illuminate\Support\Facades\DB;

class ConfirmationController extends Controller
{
    public function confirm(
        Request $request,
        string $token,
        ToolExecutor $toolExecutor
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();

        $response = DB::transaction(function () use ($token, $toolExecutor, $user, $workspace): array {
            $confirmation = ActionConfirmation::query()
                ->where('workspace_id', $workspace->id)
                ->where('token_hash', hash('sha256', $token))
                ->with('message.conversation')
                ->lockForUpdate()
                ->firstOrFail();

            $this->guardConfirmation($confirmation, $user->id);

            $confirmation->forceFill([
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
                'status' => 'confirmed',
            ])->save();

            try {
                $result = $toolExecutor->confirm(
                    $confirmation,
                    [
                        'membership' => app('currentMembership'),
                        'user' => $user,
                        'workspace' => $workspace,
                    ]
                );

                $confirmation->forceFill([
                    'executed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'executed',
                ])->save();

                return $result;
            } catch (\Throwable $exception) {
                $confirmation->forceFill([
                    'error_code' => method_exists($exception, 'getCode') && $exception->getCode()
                        ? (string) $exception->getCode()
                        : 'CONFIRMATION_EXECUTION_FAILED',
                    'error_message' => $exception->getMessage(),
                    'status' => 'failed',
                ])->save();

                throw $exception;
            }
        });

        return response()->json([
            'data' => [
                ...$response,
                'confirmation' => [
                    'status' => 'executed',
                ],
            ],
        ]);
    }

    public function cancel(
        Request $request,
        string $token
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->where('token_hash', hash('sha256', $token))
            ->with('message.conversation')
            ->firstOrFail();

        $this->guardConfirmation($confirmation, $user->id);

        $confirmation->forceFill([
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'status' => 'cancelled',
        ])->save();

        return response()->json([
            'data' => [
                'blocks' => [
                    [
                        'text' => 'La acción confirmable fue cancelada y no modificó datos.',
                        'type' => 'text',
                    ],
                    [
                        'component' => 'action.result',
                        'data' => [
                            'description' => 'La mutación quedó detenida antes de ejecutarse.',
                            'details' => [
                                [
                                    'label' => 'Acción',
                                    'value' => $confirmation->action_key,
                                ],
                            ],
                            'status' => 'partial',
                            'title' => 'Acción cancelada',
                        ],
                        'schema_version' => 1,
                        'type' => 'component',
                    ],
                ],
                'confirmation' => [
                    'status' => 'cancelled',
                ],
            ],
        ]);
    }

    public function reject(
        Request $request,
        string $token
    ) {
        return $this->cancel($request, $token);
    }

    private function guardConfirmation(
        ActionConfirmation $confirmation,
        string $userId
    ): void {
        abort_if(
            $confirmation->status !== 'pending',
            409,
            'This confirmation can no longer be executed.'
        );

        abort_if(
            $confirmation->expires_at && $confirmation->expires_at->isPast(),
            409,
            'This confirmation has expired.'
        );

        abort_if(
            $confirmation->message?->conversation?->created_by !== $userId
            && !$confirmation->message?->conversation?->participants()
                ->where('user_id', $userId)
                ->exists(),
            403,
            'You do not have access to this confirmation.'
        );
    }
}
