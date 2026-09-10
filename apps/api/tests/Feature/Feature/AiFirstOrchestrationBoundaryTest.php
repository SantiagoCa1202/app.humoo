<?php

namespace Tests\Feature\Feature;

use App\AI\Contracts\ToolCallingProvider;
use App\AI\Exceptions\AiProviderValidationException;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\AI\Orchestration\HumooSystemInstructions;
use App\AI\Orchestration\MessageLocaleResolver;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiFirstOrchestrationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('aiFirstMessages')]
    public function test_ai_first_sends_the_complete_raw_message_to_the_tool_loop_without_local_routing(
        string $content,
        bool $withActiveDraft,
    ): void {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext(
            $content,
            $withActiveDraft,
        );
        $provider = new class implements ToolCallingProvider
        {
            /** @var array<int, array<string, mixed>> */
            public array $contexts = [];

            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                $this->contexts[] = $context;
                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'Handled by the AI-first tool loop.',
                    'provider' => 'test',
                    'response_id' => 'response-ai-first',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $content, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame('Handled by the AI-first tool loop.', $assistant->content_text);
        $this->assertCount(1, $provider->contexts);
        $this->assertSame($content, $provider->contexts[0]['message']);
        $this->assertSame('auto', $provider->contexts[0]['tool_choice']);
        $this->assertDatabaseMissing('ai_objectives', ['conversation_id' => $conversation->id]);
        if ($withActiveDraft) {
            $this->assertSame('Ranch Casero', data_get($provider->contexts[0], 'operational_context.draft.name'));
        }
    }

    public function test_ai_first_fails_closed_when_the_tool_calling_provider_is_unavailable(): void
    {
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('continua');
        $assistant = $this->orchestrator(null)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => 'continua', 'locale' => 'es'],
        );

        $this->assertSame('failed', $assistant->status);
        $this->assertSame('AI_PROVIDER_UNAVAILABLE', $assistant->error_code);
    }

    public function test_tool_discovery_failure_does_not_load_the_full_catalog(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
            'ai.tool_discovery.enabled' => true,
            'ai.tool_discovery.fallback_to_full_catalog' => true,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('Muéstrame mis tareas.');
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            /** @var array<int, array<int, array<string, mixed>>> */
            public array $toolsByTurn = [];

            public function toolTurn(array $context, array $tools, ?string $previousResponseId = null, array $input = []): array
            {
                $this->toolsByTurn[] = $tools;
                $this->turns++;
                if ($this->turns === 1) {
                    throw new AiProviderValidationException('Discovery unsupported.', [
                        'provider_message' => 'Unknown tool type tool_search.',
                    ]);
                }

                return [
                    'model' => 'test-discovery-fallback',
                    'output' => [],
                    'output_text' => 'Fallback completed.',
                    'provider' => 'test',
                    'response_id' => 'response-discovery-fallback',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $message->content_text, 'locale' => 'es'],
        );

        $this->assertSame('failed', $assistant->status);
        $this->assertSame(1, $provider->turns);
        $this->assertNotNull(collect($provider->toolsByTurn[0])->firstWhere('type', 'tool_search'));
        $namespaces = collect($provider->toolsByTurn[0])->where('type', 'namespace');
        $this->assertNotEmpty($namespaces);
        $this->assertTrue($namespaces->every(fn (array $namespace): bool => count($namespace['tools'] ?? []) < 10));
        $this->assertTrue($namespaces->pluck('name')->contains('tasks_query'));
        $this->assertTrue($namespaces->pluck('name')->contains('tasks_mutation'));
        $this->assertTrue($namespaces->pluck('name')->contains('menu_items'));
        $this->assertTrue($namespaces->flatMap(fn (array $namespace): array => $namespace['tools'] ?? [])->contains(
            fn (array $tool): bool => ($tool['defer_loading'] ?? false) === true,
        ));
    }

    public function test_recoverable_tool_protocol_errors_return_to_the_same_ai_loop(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        $content = 'haz una importacion que no existe y dime que alternativas hay';
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext($content);
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            /** @var array<int, array<string, mixed>> */
            public array $secondInput = [];

            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                $this->turns++;
                if ($this->turns === 1) {
                    return [
                        'model' => 'test-ai-first',
                        'output' => [[
                            'type' => 'function_call',
                            'name' => 'unregistered_capability',
                            'call_id' => 'call-unsupported',
                            'arguments' => '{}',
                        ]],
                        'provider' => 'test',
                        'response_id' => 'response-unsupported',
                        'usage' => [],
                    ];
                }

                $this->secondInput = $input;

                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'I could not use that tool, so I replanned.',
                    'provider' => 'test',
                    'response_id' => 'response-replanned',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $content, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame(2, $provider->turns);
        $toolOutput = collect($provider->secondInput)->firstWhere('type', 'function_call_output');
        $this->assertIsArray($toolOutput);
        $this->assertSame('TOOL_NOT_FOUND', data_get(json_decode($toolOutput['output'], true, 512, JSON_THROW_ON_ERROR), 'error.code'));
    }

    public function test_read_result_returns_complete_data_to_the_model_before_a_direct_response(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        $content = 'Busca los miembros del workspace y dime cuántos hay.';
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext($content);
        foreach (['Sous Chef One', 'Sous Chef Two'] as $name) {
            $memberUser = User::factory()->create(['name' => $name]);
            WorkspaceMembership::query()->create([
                'joined_at' => now(),
                'role_id' => $membership->role_id,
                'status' => 'active',
                'user_id' => $memberUser->id,
                'workspace_id' => $workspace->id,
            ]);
        }
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            /** @var array<int, array<string, mixed>> */
            public array $continuationInput = [];

            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                $this->turns++;
                if ($this->turns === 1) {
                    return [
                        'model' => 'test-ai-first',
                        'output' => [[
                            'type' => 'function_call',
                            'name' => 'members_list',
                            'call_id' => 'call-members',
                            'arguments' => '{"search":null,"limit":20}',
                        ]],
                        'provider' => 'test',
                        'response_id' => 'response-members',
                        'usage' => [],
                    ];
                }

                $this->continuationInput = $input;

                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'La lista autorizada de miembros fue consultada.',
                    'provider' => 'test',
                    'response_id' => 'response-members-final',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $content, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame(2, $provider->turns);
        $toolOutput = collect($provider->continuationInput)
            ->first(fn (array $item): bool => ($item['type'] ?? null) === 'function_call_output'
                && ($item['call_id'] ?? null) === 'call-members');
        $this->assertNotNull($toolOutput);
        $observation = json_decode($toolOutput['output'], true, 512, JSON_THROW_ON_ERROR);
        $owner = collect(data_get($observation, 'data.result.items', []))
            ->first(fn (array $item): bool => data_get($item, 'user.email') === 'owner@humoo.local');
        $this->assertSame('Humoo Owner', data_get($owner, 'user.name'));
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame(['members.list'], $run->toolCalls()->orderBy('position')->pluck('tool_key')->all());
        $this->assertSame('completed', data_get($run->fresh()->metadata, 'termination_reason'));
        $state = data_get($conversation->fresh()->metadata, 'ai_operational_context');
        $this->assertSame('members.list', data_get($state, 'candidate_sets.0.source_action'));
        $this->assertSame('active', data_get($state, 'candidate_sets.0.status'));
        $this->assertDatabaseMissing('ai_objectives', ['conversation_id' => $conversation->id]);
    }

    public function test_provider_plain_text_is_the_normal_terminal_response(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('Muéstrame mis miembros.');
        $provider = new class implements ToolCallingProvider
        {
            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'Invented response without a tool.',
                    'provider' => 'test',
                    'response_id' => 'response-invalid-plain-text',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $message->content_text, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame('Invented response without a tool.', $assistant->content_text);
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame('completed', data_get($run->metadata, 'termination_reason'));
        $this->assertSame(0, data_get($run->metadata, 'tool_count'));
    }

    public function test_tool_loop_iteration_limit_is_recorded_explicitly(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
            'ai.max_orchestration_iterations' => 1,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('Muéstrame mis miembros.');
        $provider = new class implements ToolCallingProvider
        {
            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                return [
                    'model' => 'test-ai-first',
                    'output' => [[
                        'type' => 'function_call',
                        'name' => 'members_list',
                        'call_id' => 'call-members-before-limit',
                        'arguments' => '{}',
                    ]],
                    'provider' => 'test',
                    'response_id' => 'response-before-limit',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $message->content_text, 'locale' => 'es'],
        );

        $this->assertSame('failed', $assistant->status);
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame('tool_iteration_limit', data_get($run->metadata, 'termination_reason'));
        $this->assertSame(1, data_get($run->metadata, 'tool_count'));
    }

    public function test_partial_recipe_creation_starts_with_the_domain_tool_before_clarifying(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        $content = 'crea una receta ranch';
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext($content);
        app()->instance('currentWorkspace', $workspace);
        app()->instance('currentMembership', $membership);
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                $this->turns++;
                if ($this->turns === 1) {
                    return [
                        'model' => 'test-ai-first',
                        'output' => [[
                            'type' => 'function_call',
                            'name' => 'recipes_create',
                            'call_id' => 'call-ranch-draft',
                            'arguments' => json_encode([
                                'name' => 'Ranch',
                                'description' => null,
                                'yield' => null,
                                'ingredients' => [],
                                'steps' => [],
                                'source' => 'structured_ai',
                            ], JSON_THROW_ON_ERROR),
                        ]],
                        'provider' => 'test',
                        'response_id' => 'response-ranch-draft',
                        'usage' => [],
                    ];
                }

                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'Necesito el rendimiento, los ingredientes y los pasos de la receta.',
                    'provider' => 'test',
                    'response_id' => 'response-ranch-clarification',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $content, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame(2, $provider->turns);
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame(['recipes.create'], $run->toolCalls()->orderBy('position')->pluck('tool_key')->all());
        $this->assertSame('clarification_required', data_get($run->metadata, 'termination_reason'));
        $this->assertSame('recipes.create', data_get(
            $conversation->fresh()->metadata,
            'active_recipe_draft_state.action_key',
        ));
        $this->assertSame('needs_clarification', data_get(
            $conversation->fresh()->metadata,
            'active_recipe_draft_state.status',
        ));
        $pendingCalls = collect(data_get(
            $conversation->fresh()->metadata,
            'pending_provider_tool_outputs',
            [],
        ));
        $this->assertNull($pendingCalls->firstWhere('call_id', 'call-ranch-draft'));
        $this->assertNull($pendingCalls->firstWhere('call_id', 'call-ranch-clarification'));
    }

    public function test_generic_clarification_is_returned_directly_without_duplicate_state(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        $content = 'Create the records and assign them to a member who is not in this workspace.';
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext($content);
        $provider = new class implements ToolCallingProvider
        {
            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'I could not find that member. Who should receive the assignments?',
                    'provider' => 'test',
                    'response_id' => 'response-generic-clarification',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $content, 'locale' => 'en'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame(
            'I could not find that member. Who should receive the assignments?',
            $assistant->content_text,
        );
        $this->assertSame([], data_get($conversation->fresh()->metadata, 'pending_clarifications', []));
        $this->assertDatabaseMissing('ai_objectives', ['conversation_id' => $conversation->id]);
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame('completed', data_get($run->metadata, 'termination_reason'));
    }

    public function test_model_cancels_the_active_objective_with_the_explicit_tool(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('cancela todo');
        $objective = app(AiObjectiveLifecycle::class)->startOrResume(
            $conversation,
            $workspace,
            $user,
            $message,
            'Prepare pending work',
        );
        $operation = $objective->operations()->create([
            'workspace_id' => $workspace->id,
            'operation_key' => 'operation_1',
            'action_key' => 'recipes.create',
            'kind' => 'write',
            'status' => 'pending',
            'is_required' => true,
        ]);
        $objective->forceFill(['operation_count' => 1, 'pending_count' => 1, 'status' => 'waiting_user'])->save();
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            /** @var array<int, array<string, mixed>> */
            public array $secondInput = [];

            public function toolTurn(array $context, array $tools, ?string $previousResponseId = null, array $input = []): array
            {
                $this->turns++;
                if ($this->turns === 1) {
                    return [
                        'model' => 'test-ai-first',
                        'output' => [[
                            'type' => 'function_call',
                            'name' => 'objectives_cancel',
                            'call_id' => 'call-cancel-objective',
                            'arguments' => '{"reason":"The user cancelled the active work."}',
                        ]],
                        'provider' => 'test',
                        'response_id' => 'response-cancel-objective',
                        'usage' => [],
                    ];
                }

                $this->secondInput = $input;

                return [
                    'model' => 'test-ai-first',
                    'output' => [],
                    'output_text' => 'Cancelé todo el trabajo pendiente. No revertí cambios ya ejecutados.',
                    'provider' => 'test',
                    'response_id' => 'response-cancelled-final',
                    'usage' => [],
                ];
            }
        };
        $assistant = $this->orchestrator($provider)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $message->content_text, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame('Cancelé todo el trabajo pendiente. No revertí cambios ya ejecutados.', $assistant->content_text);
        $this->assertSame('cancelled', $objective->fresh()->status);
        $this->assertSame('cancelled', $operation->fresh()->status);
        $this->assertNull(data_get($conversation->fresh()->metadata, 'active_ai_objective_id'));
        $toolOutput = collect($provider->secondInput)
            ->first(fn (array $item): bool => ($item['type'] ?? null) === 'function_call_output'
                && ($item['call_id'] ?? null) === 'call-cancel-objective');
        $observation = json_decode($toolOutput['output'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($observation['ok']);
        $this->assertTrue(data_get($observation, 'data.result.cancelled'));
        $run = AiRun::query()->where('input_message_id', $message->id)->firstOrFail();
        $this->assertSame('cancelled', $run->status);
    }

    public function test_locale_comes_from_explicit_or_profile_state_without_text_parsing(): void
    {
        $workspace = new Workspace(['default_locale' => 'en']);
        $user = new User(['locale' => 'en']);
        $resolver = new MessageLocaleResolver;
        $spanishRecipe = 'crea esta receta con ingredientes y preparacion para cuatro porciones';

        $this->assertSame('en', $resolver->resolve(null, $spanishRecipe, $workspace, $user));
        $this->assertSame('es', $resolver->resolve('es', $spanishRecipe, $workspace, $user));
    }

    public function test_unplanned_scope_rejects_a_models_success_claim_and_returns_repair_feedback(): void
    {
        config(['ai.chat_streaming_enabled' => false, 'ai.conversations.enabled' => false]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('Create a client and assign two tasks.');
        $provider = new class implements ToolCallingProvider
        {
            public array $inputs = [];

            public function toolTurn(array $context, array $tools, ?string $previousResponseId = null, array $input = []): array
            {
                $this->inputs[] = $input;
                return ['model' => 'test', 'provider' => 'test', 'usage' => [], 'response_id' => 'scope-'.count($this->inputs),
                    'output_text' => 'Everything is done.',
                    'output' => count($this->inputs) === 1 ? [[
                        'type' => 'function_call', 'name' => 'objectives_define', 'call_id' => 'define-scope',
                        'arguments' => json_encode(['required_facts' => [], 'expected_results' => [
                            ['result_key' => 'client', 'label' => 'Create client', 'required' => true],
                            ['result_key' => 'tasks', 'label' => 'Assign two tasks', 'required' => true],
                        ]]),
                    ]] : []];
            }
        };
        $assistant = $this->orchestrator($provider)->respond($conversation, $workspace, $membership, $user, $message,
            ['content' => $message->content_text, 'locale' => 'es']);

        $this->assertCount(4, $provider->inputs);
        $this->assertStringContainsString('missing_expected_results', json_encode($provider->inputs[2]));
        $this->assertNotSame('Everything is done.', $assistant->content_text);
        $this->assertSame('partial', data_get($assistant->metadata, 'orchestration.workflow_status'));
        $run = AiRun::where('input_message_id', $message->id)->firstOrFail();
        $this->assertNotSame('completed', $run->status);
        $this->assertSame('partial', $run->objective->status);
        $this->assertCount(2, $run->objective->expected_results_json);
    }

    public function test_provider_timeout_is_deferred_without_an_inline_replay(): void
    {
        config(['ai.chat_streaming_enabled' => false, 'ai.conversations.enabled' => false]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('Show my tasks');
        $provider = new class implements ToolCallingProvider
        {
            public int $turns = 0;

            public function toolTurn(array $context, array $tools, ?string $previousResponseId = null, array $input = []): array
            {
                $this->turns++;
                throw new \App\AI\Exceptions\AiProviderTimeoutException('The remote turn has an unknown outcome.');
            }
        };
        $assistant = $this->orchestrator($provider)->respond($conversation, $workspace, $membership, $user, $message,
            ['content' => $message->content_text, 'locale' => 'es']);

        $this->assertSame(1, $provider->turns);
        $this->assertTrue(data_get($conversation->fresh()->metadata, 'provider_recovery.pending'));
        $this->assertSame('AI_TIMEOUT', $assistant->error_code);
    }

    /** @return array<string, array{string, bool}> */
    public static function aiFirstMessages(): array
    {
        return [
            'create recipe' => ['crea una receta ranch', false],
            'change referenced recipe' => ['cambia la sal del ranch', true],
            'short continuation' => ['hazlo 1 tbsp', true],
            'create related batch' => ['ahora crea otras 5 parecidas', true],
            'connect prior results' => ['conéctalas al menú de ayer', true],
            'read final state' => ['muéstrame todo', true],
            'delete prior subset' => ['borra las últimas dos', true],
            'replan prior operation' => ['no, mejor déjalas y solo cambia la primera', true],
            'generic continuation' => ['continúa', true],
            'multi action' => [
                'crea un menú italiano, crea las recetas que falten, conéctalas al menú, asigna a Jennifer la preparación y después muéstrame todo',
                false,
            ],
        ];
    }

    /** @return array{Conversation, Workspace, WorkspaceMembership, User, Message} */
    private function chatContext(string $content, bool $withActiveDraft = false): array
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
        $metadata = $withActiveDraft ? [
            'active_recipe_draft_state' => [
                'action_key' => 'recipes.create',
                'actor_id' => $user->id,
                'draft_id' => 'recipe-draft-1',
                'conversation_id' => null,
                'payload' => ['name' => 'Ranch Casero'],
                'missing_fields' => ['yield.quantity'],
                'issues' => [],
                'revision' => 1,
                'status' => 'needs_clarification',
                'workspace_id' => $workspace->id,
            ],
        ] : [];
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'metadata' => $metadata,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'AI-first boundary',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        if ($withActiveDraft) {
            $metadata['active_recipe_draft_state']['conversation_id'] = $conversation->id;
            $conversation->forceFill(['metadata' => $metadata])->save();
        }
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $message = Message::query()->create([
            'content_text' => $content,
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'streaming',
            'workspace_id' => $workspace->id,
        ]);

        return [$conversation, $workspace, $membership, $user, $message];
    }

    private function orchestrator(?ToolCallingProvider $provider): AIOrchestrator
    {
        return new AIOrchestrator(
            systemInstructions: app(HumooSystemInstructions::class),
            assistantMessageWriter: app(AssistantMessageWriter::class),
            recordConversationEntityRefs: app(RecordConversationEntityRefs::class),
            toolExecutor: app(ToolExecutor::class),
            toolRegistry: app(ToolRegistry::class),
            conversationContinuationLifecycle: app(ConversationContinuationLifecycle::class),
            messageLocaleResolver: app(MessageLocaleResolver::class),
            toolCallingProvider: $provider,
        );
    }
}
