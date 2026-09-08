<?php

namespace Tests\Feature\Feature;

use App\AI\Contracts\ToolCallingProvider;
use App\AI\Exceptions\AiProviderTimeoutException;
use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\HumooSystemInstructions;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Application\Actions\Chat\RecordUnsupportedCapability;
use App\Models\CapabilityRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CapabilityRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_request_is_answered_by_the_model_without_local_intent_recording(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();

        $assistant = $this->orchestrator($this->provider([
            ['output' => [], 'output_text' => 'Humoo does not support sending prep lists to suppliers yet.'],
        ]));

        $result = $assistant->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertSame('completed', $result->status);
        $this->assertStringContainsString('does not support', (string) $result->content_text);
        $this->assertDatabaseCount('capability_requests', 0);
        $this->assertDatabaseCount('ai_tool_calls', 0);
    }

    public function test_equivalent_requests_increment_occurrences(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $action = app(RecordUnsupportedCapability::class);

        $action->execute($workspace, $user, $conversation, $message, [
            'detected_intent' => 'send_prep_to_supplier',
            'module' => 'purchasing',
            'requested_action' => 'send prep list to supplier',
        ]);

        $secondMessage = $this->createMessage($conversation, $workspace, $user, 'Envía la preparación al proveedor.');
        $record = $action->execute($workspace, $user, $conversation, $secondMessage, [
            'detected_intent' => 'enviar_preparacion_al_proveedor',
            'module' => 'compras',
            'requested_action' => 'envia la preparacion al proveedor',
        ]);

        $this->assertSame(2, $record->occurrences);
        $this->assertSame(
            'purchasing.send_prep_to_supplier',
            $record->normalized_key
        );
        $this->assertDatabaseCount('capability_requests', 1);
    }

    public function test_equivalent_requests_are_isolated_by_workspace(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        [$otherWorkspace, $otherConversation, $otherMessage] = $this->otherWorkspaceScenario($user);
        $action = app(RecordUnsupportedCapability::class);
        $payload = [
            'detected_intent' => 'send_prep_to_supplier',
            'module' => 'purchasing',
            'normalized_key' => 'purchasing.send_prep_to_supplier',
            'requested_action' => 'send prep list to supplier',
        ];

        $action->execute($workspace, $user, $conversation, $message, $payload);
        $action->execute($otherWorkspace, $user, $otherConversation, $otherMessage, $payload);

        $this->assertDatabaseCount('capability_requests', 2);
        $this->assertDatabaseHas('capability_requests', [
            'occurrences' => 1,
            'workspace_id' => $workspace->id,
        ]);
        $this->assertDatabaseHas('capability_requests', [
            'occurrences' => 1,
            'workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_existing_tool_does_not_create_capability_request(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('request')->once()->andReturn([
            'blocks' => [
                ['text' => 'There are no events.', 'type' => 'text'],
            ],
            'result_ref_json' => ['count' => 0, 'items' => []],
        ]);

        $assistant = $this->orchestrator($this->provider([
            $this->toolCall('events_list', 'call-events-list'),
            ['output' => [], 'output_text' => 'There are no events.'],
        ]), $toolExecutor);

        $assistant->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertDatabaseCount('capability_requests', 0);
    }

    public function test_tool_execution_error_does_not_create_capability_request(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('request')->once()->andThrow(new RuntimeException('tool failed'));

        $assistant = $this->orchestrator($this->provider([
            $this->toolCall('events_list', 'call-events-error'),
            ['output' => [], 'output_text' => 'I could not retrieve the events.'],
        ]), $toolExecutor);

        $result = $assistant->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertSame('completed', $result->status);
        $this->assertDatabaseCount('capability_requests', 0);
    }

    public function test_exhausted_plan_repair_stops_without_an_extra_provider_turn(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('request')->twice()->andThrow(
            ValidationException::withMessages([
                'completion_steps' => ['Verification is required.'],
                'unverified_results' => ['recipes_created'],
            ])
        );
        $provider = Mockery::mock(ToolCallingProvider::class);
        $provider->shouldReceive('toolTurn')->twice()->andReturn(
            [
                'model' => 'test-model',
                'provider' => 'test',
                'response_id' => 'response-1',
                'usage' => [],
                ...$this->toolCall('execution_plans_create', 'call-plan-1', [
                    'title' => 'Plan one',
                    'steps' => [['step_key' => 'create_recipes']],
                ]),
            ],
            [
                'model' => 'test-model',
                'provider' => 'test',
                'response_id' => 'response-2',
                'usage' => [],
                ...$this->toolCall('execution_plans_create', 'call-plan-2', [
                    'title' => 'Plan repaired',
                    'steps' => [['step_key' => 'create_recipes']],
                ]),
            ],
        );

        $result = $this->orchestrator($provider, $toolExecutor)->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame('WORKFLOW_RETRY_EXHAUSTED', $result->error_code);
        $this->assertDatabaseHas('ai_runs', ['status' => 'paused', 'error_code' => 'WORKFLOW_RETRY_EXHAUSTED']);
        $this->assertDatabaseHas('ai_objectives', ['status' => 'paused', 'error_code' => 'WORKFLOW_RETRY_EXHAUSTED']);
        $pendingOutputs = data_get($conversation->fresh()->metadata, 'pending_provider_tool_outputs', []);
        $this->assertCount(1, $pendingOutputs);
        $this->assertSame('call-plan-2', $pendingOutputs[0]['call_id']);
        $this->assertIsArray($pendingOutputs[0]['output']);
    }

    public function test_provider_failure_uses_an_internal_code_and_executes_no_tool(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldNotReceive('request');
        $provider = new class implements ToolCallingProvider
        {
            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array
            {
                throw new AiProviderTimeoutException(
                    'The OpenAI request timed out.',
                    ['provider' => 'openai', 'model' => 'test-model']
                );
            }
        };

        $result = $this->orchestrator($provider, $toolExecutor)->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertSame('failed', $result->status);
        $this->assertSame('AI_TIMEOUT', $result->error_code);
        $this->assertDatabaseHas('ai_runs', [
            'error_code' => 'AI_TIMEOUT',
            'status' => 'paused',
        ]);
        $this->assertDatabaseCount('ai_tool_calls', 0);
    }

    public function test_permission_denied_does_not_create_capability_request(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $toolExecutor = Mockery::mock(ToolExecutor::class);
        $toolExecutor->shouldReceive('request')->once()->andThrow(new AuthorizationException());

        $assistant = $this->orchestrator($this->provider([
            $this->toolCall('events_list', 'call-events-denied'),
            ['output' => [], 'output_text' => 'You do not have permission to view those events.'],
        ]), $toolExecutor);

        $assistant->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertDatabaseCount('capability_requests', 0);
    }

    public function test_ambiguous_intent_does_not_create_capability_request(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();
        $assistant = $this->orchestrator($this->provider([
            ['output' => [], 'output_text' => 'Which workspace area do you mean?'],
        ]));

        $assistant->respond(
            $conversation,
            $workspace,
            $user->membershipForWorkspace($workspace->id),
            $user,
            $message,
            ['locale' => 'en']
        );

        $this->assertDatabaseCount('capability_requests', 0);
    }

    public function test_capability_action_rejects_cross_workspace_context(): void
    {
        [$workspace, $user] = $this->scenario();
        [, $foreignConversation, $foreignMessage] = $this->otherWorkspaceScenario($user);

        $this->expectException(ValidationException::class);

        app(RecordUnsupportedCapability::class)->execute(
            $workspace,
            $user,
            $foreignConversation,
            $foreignMessage,
            [
                'detected_intent' => 'send_prep_to_supplier',
                'requested_action' => 'send prep list to supplier',
            ]
        );
    }

    public function test_sensitive_metadata_is_not_persisted(): void
    {
        [$workspace, $user, $conversation, $message] = $this->scenario();

        $record = app(RecordUnsupportedCapability::class)->execute(
            $workspace,
            $user,
            $conversation,
            $message,
            [
                'detected_intent' => 'send_prep_to_supplier',
                'requested_action' => 'send prep list to supplier',
                'metadata' => [
                    'content' => 'The user full private message.',
                    'provider' => 'openai',
                    'secret' => 'do-not-store',
                ],
            ]
        );

        $this->assertSame(['provider' => 'openai'], $record->metadata_json);
        $this->assertStringNotContainsString('private message', json_encode($record->metadata_json));
        $this->assertStringNotContainsString('do-not-store', json_encode($record->metadata_json));
    }

    private function scenario(): array
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
        $conversation = $this->createConversation($workspace, $user);
        $message = $this->createMessage($conversation, $workspace, $user, 'Send the prep list to the supplier.');

        return [$workspace, $user, $conversation, $message];
    }

    private function otherWorkspaceScenario(User $user): array
    {
        $ownerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'owner')
            ->firstOrFail();
        $workspace = Workspace::query()->create([
            'currency' => 'USD',
            'default_locale' => 'en',
            'name' => 'Other Kitchen',
            'slug' => 'other-kitchen',
            'status' => 'active',
            'timezone' => 'America/New_York',
        ]);
        WorkspaceMembership::query()->create([
            'joined_at' => now(),
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $conversation = $this->createConversation($workspace, $user);
        $message = $this->createMessage($conversation, $workspace, $user, 'Send the prep list to the supplier.');

        return [$workspace, $conversation, $message];
    }

    private function createConversation(Workspace $workspace, User $user): Conversation
    {
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Capability requests',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);

        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);

        return $conversation;
    }

    private function createMessage(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        string $content
    ): Message {
        return Message::query()->create([
            'content_text' => $content,
            'conversation_id' => $conversation->id,
            'locale' => 'en',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
    }

    /** @param array<int, array<string, mixed>> $turns */
    private function provider(array $turns): ToolCallingProvider
    {
        return new class($turns) implements ToolCallingProvider
        {
            private int $index = 0;

            public function __construct(private array $turns)
            {
            }

            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                $turn = $this->turns[$this->index++] ?? end($this->turns);

                return [
                    'model' => 'test-model',
                    'provider' => 'test',
                    'response_id' => 'response-'.$this->index,
                    'usage' => [],
                    ...$turn,
                ];
            }
        };
    }

    private function orchestrator(
        ToolCallingProvider $provider,
        ?ToolExecutor $toolExecutor = null
    ): AIOrchestrator {
        config()->set('ai.chat_streaming_enabled', false);
        config()->set('ai.retry_budgets.provider_transient_backoff_ms', 0);
        config()->set('ai.retry_budgets.provider_transient_max_backoff_ms', 0);

        $registry = new ToolRegistry();
        $executor = $toolExecutor ?? Mockery::mock(ToolExecutor::class);

        return new AIOrchestrator(
            systemInstructions: new HumooSystemInstructions(),
            assistantMessageWriter: new AssistantMessageWriter(),
            recordConversationEntityRefs: app(RecordConversationEntityRefs::class),
            toolExecutor: $executor,
            toolRegistry: $registry,
            conversationContinuationLifecycle: app(\App\AI\Orchestration\ConversationContinuationLifecycle::class),
            messageLocaleResolver: app(\App\AI\Orchestration\MessageLocaleResolver::class),
            toolCallingProvider: $provider,
        );
    }

    /** @return array<string, mixed> */
    private function toolCall(string $name, string $callId, array $arguments = []): array
    {
        return [
            'output' => [[
                'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR),
                'call_id' => $callId,
                'name' => $name,
                'type' => 'function_call',
            ]],
            'output_text' => '',
        ];
    }
}
