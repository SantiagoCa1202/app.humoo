<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssistantResponseResource;
use App\Models\ActionConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfirmationController extends Controller
{
    public function confirm(
        Request $request,
        string $token,
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter,
        RecordConversationEntityRefs $recordConversationEntityRefs
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $overrideInput = $request->input('input');

        $response = DB::transaction(function () use (
            $assistantMessageWriter,
            $recordConversationEntityRefs,
            $token,
            $toolExecutor,
            $user,
            $workspace,
            $overrideInput
        ): array {
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
                        'locale' => $confirmation->message?->locale,
                        'membership' => app('currentMembership'),
                        'user' => $user,
                        'workspace' => $workspace,
                    ],
                    is_array($overrideInput) ? $overrideInput : null
                );

                $confirmation->forceFill([
                    'executed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'executed',
                ])->save();

                $recordConversationEntityRefs->execute(
                    $confirmation->message->conversation,
                    $workspace,
                    $result['entity_refs'] ?? []
                );

                $assistantMessage = $assistantMessageWriter->create(
                    $confirmation->message->conversation,
                    $workspace,
                    $confirmation->message->locale,
                    [
                        'blocks' => $result['blocks'] ?? [],
                        'suggestions' => [],
                    ],
                    $confirmation->message,
                    [
                        'source' => 'confirmation-result',
                    ]
                );

                return [
                    'assistant_response' => new AssistantResponseResource(
                        $assistantMessage->load('blocks')
                    ),
                    'confirmation' => [
                        'id' => $confirmation->id,
                        'status' => 'executed',
                        'token' => null,
                    ],
                    'conversation' => [
                        'id' => $assistantMessage->conversation_id,
                        'last_message_at' => $assistantMessage->conversation()->first()?->last_message_at?->toIso8601String(),
                    ],
                    'tool' => $result['tool'] ?? null,
                ];
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
            'data' => $response,
        ]);
    }

    public function cancel(
        Request $request,
        string $token,
        AssistantMessageWriter $assistantMessageWriter
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

        $assistantMessage = $assistantMessageWriter->create(
            $confirmation->message->conversation,
            $workspace,
            $confirmation->message->locale,
            [
                'blocks' => [
                    [
                        'text' => 'La accion confirmable fue cancelada y no modifico datos.',
                        'type' => 'text',
                    ],
                    [
                        'component' => 'action.result',
                        'data' => [
                            'description' => 'La mutacion quedo detenida antes de ejecutarse.',
                            'details' => [
                                [
                                    'label' => 'Accion',
                                    'value' => $confirmation->action_key,
                                ],
                            ],
                            'status' => 'partial',
                            'title' => 'Accion cancelada',
                        ],
                        'schema_version' => 1,
                        'type' => 'component',
                    ],
                ],
                'suggestions' => [],
            ],
            $confirmation->message,
            [
                'source' => 'confirmation-cancelled',
            ]
        );

        return response()->json([
            'data' => [
                'assistant_response' => new AssistantResponseResource(
                    $assistantMessage->load('blocks')
                ),
                'confirmation' => [
                    'id' => $confirmation->id,
                    'status' => 'cancelled',
                    'token' => null,
                ],
                'conversation' => [
                    'id' => $assistantMessage->conversation_id,
                    'last_message_at' => $assistantMessage->conversation()->first()?->last_message_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function reject(
        Request $request,
        string $token,
        AssistantMessageWriter $assistantMessageWriter
    ) {
        return $this->cancel($request, $token, $assistantMessageWriter);
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
