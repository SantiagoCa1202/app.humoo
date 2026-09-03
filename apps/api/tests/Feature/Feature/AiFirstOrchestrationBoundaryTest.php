<?php

namespace Tests\Feature\Feature;

use App\AI\Advisory\AdvisoryOrchestrator;
use App\AI\Advisory\RecipeDraftPayloadMapper;
use App\AI\Capabilities\CapabilityFunctionRouter;
use App\AI\Clarifications\PendingClarificationResolver;
use App\AI\Contracts\ToolCallingProvider;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Intent\MessageShapeDetector;
use App\AI\Intent\RoutingDecisionValidator;
use App\AI\Fallback\SemanticFallbackOrchestrator;
use App\AI\Menu\MenuDraftParser;
use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\ContinuationResolver;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\AI\Orchestration\HumooSystemInstructions;
use App\AI\Orchestration\LegacySemanticServices;
use App\AI\Orchestration\MessageLocaleResolver;
use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Recipes\RecipeInputIngestionPipeline;
use App\AI\Recipes\RecipeStructuredExtractor;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Application\Actions\Chat\RecordUnsupportedCapability;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AiFirstOrchestrationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('aiFirstMessages')]
    public function test_ai_first_sends_the_complete_raw_message_to_the_tool_loop_without_local_routing(
        string $content,
        bool $withPendingDraft,
    ): void {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
            'ai.routing.tool_loop_enabled' => true,
        ]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext(
            $content,
            $withPendingDraft,
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
        $router = Mockery::mock(HybridIntentRouter::class);
        $router->shouldNotReceive('route');

        $assistant = $this->orchestrator($router, $provider)->respond(
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
        if ($withPendingDraft) {
            $this->assertNotEmpty($provider->contexts[0]['pending_continuations']);
        }
    }

    public function test_ai_first_fails_closed_when_the_tool_calling_provider_is_unavailable(): void
    {
        config(['ai.routing.tool_loop_enabled' => true]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('continua');
        $router = Mockery::mock(HybridIntentRouter::class);
        $router->shouldNotReceive('route');

        $assistant = $this->orchestrator($router, null)->respond(
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

    public function test_recoverable_tool_protocol_errors_return_to_the_same_ai_loop(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
            'ai.routing.tool_loop_enabled' => true,
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
        $router = Mockery::mock(HybridIntentRouter::class);
        $router->shouldNotReceive('route');
        $unsupported = Mockery::mock(RecordUnsupportedCapability::class);
        $unsupported->shouldNotReceive('execute');

        $assistant = $this->orchestrator($router, $provider, $unsupported)->respond(
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
        $this->assertSame('TOOL_NOT_FOUND', json_decode($toolOutput['output'], true, 512, JSON_THROW_ON_ERROR)['code']);
    }

    public function test_ai_first_resolves_and_runs_when_all_legacy_semantic_services_are_disabled(): void
    {
        config([
            'ai.chat_streaming_enabled' => false,
            'ai.conversations.enabled' => false,
            'ai.routing.tool_loop_enabled' => true,
        ]);
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
                    'output_text' => 'AI-first remained available.',
                    'provider' => 'test',
                    'response_id' => 'response-no-legacy',
                    'usage' => [],
                ];
            }
        };
        $this->app->bind(ToolCallingProvider::class, fn (): ToolCallingProvider => $provider);
        foreach ([
            LegacySemanticServices::class,
            HybridIntentRouter::class,
            ContinuationResolver::class,
            RuleBasedAIProvider::class,
            MessageShapeDetector::class,
            IntentPatternRegistry::class,
            SemanticFallbackOrchestrator::class,
            RecipeInputIngestionPipeline::class,
            RecipeStructuredExtractor::class,
            MenuDraftParser::class,
        ] as $legacyService) {
            $this->app->bind($legacyService, static function () use ($legacyService): never {
                throw new RuntimeException("Legacy service resolved: {$legacyService}");
            });
        }

        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('continúa con lo pendiente');
        $assistant = app(AIOrchestrator::class)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => $message->content_text, 'locale' => 'es'],
        );

        $this->assertSame('completed', $assistant->status);
        $this->assertSame('AI-first remained available.', $assistant->content_text);
    }

    public function test_legacy_router_is_reachable_only_when_the_tool_loop_flag_is_disabled(): void
    {
        config(['ai.routing.tool_loop_enabled' => false]);
        [$conversation, $workspace, $membership, $user, $message] = $this->chatContext('show my events');
        $router = Mockery::mock(HybridIntentRouter::class);
        $router->shouldReceive('route')
            ->once()
            ->andThrow(new RuntimeException('legacy route reached'));

        $assistant = $this->orchestrator($router, null)->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $message,
            ['content' => 'show my events', 'locale' => 'en'],
        );

        $this->assertSame('failed', $assistant->status);
        $this->assertSame('INTERNAL_ERROR', $assistant->error_code);
    }

    public function test_locale_keyword_detection_is_legacy_only(): void
    {
        $workspace = new Workspace(['default_locale' => 'en']);
        $user = new User(['locale' => 'en']);
        $resolver = new MessageLocaleResolver();
        $spanishRecipe = 'crea esta receta con ingredientes y preparacion para cuatro porciones';

        config(['ai.routing.tool_loop_enabled' => true]);
        $this->assertSame('en', $resolver->resolve(null, $spanishRecipe, $workspace, $user));

        config(['ai.routing.tool_loop_enabled' => false]);
        $this->assertSame('es', $resolver->resolve(null, $spanishRecipe, $workspace, $user));
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
    private function chatContext(string $content, bool $withPendingDraft = false): array
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
        $metadata = $withPendingDraft ? [
            'pending_continuations' => [[
                'action_key' => 'recipes.create',
                'actor_id' => $user->id,
                'continuation_id' => 'recipe-draft-1',
                'conversation_id' => null,
                'entity_type' => 'recipe',
                'kind' => 'draft',
                'label' => 'Ranch Casero',
                'payload' => ['name' => 'Ranch Casero'],
                'status' => 'pending',
                'target_type' => 'recipe_draft',
                'workspace_id' => $workspace->id,
            ]],
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
        if ($withPendingDraft) {
            $metadata['pending_continuations'][0]['conversation_id'] = $conversation->id;
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

    private function orchestrator(
        HybridIntentRouter $router,
        ?ToolCallingProvider $provider,
        ?RecordUnsupportedCapability $unsupportedRecorder = null,
    ): AIOrchestrator {
        return new AIOrchestrator(
            systemInstructions: app(HumooSystemInstructions::class),
            assistantMessageWriter: app(AssistantMessageWriter::class),
            recordConversationEntityRefs: app(RecordConversationEntityRefs::class),
            toolExecutor: app(ToolExecutor::class),
            toolRegistry: app(ToolRegistry::class),
            conversationContinuationLifecycle: app(ConversationContinuationLifecycle::class),
            messageLocaleResolver: app(MessageLocaleResolver::class),
            toolCallingProvider: $provider,
            legacySemanticServicesFactory: fn (): LegacySemanticServices => new LegacySemanticServices(
                hybridIntentRouter: $router,
                intentPatternRegistry: app(IntentPatternRegistry::class),
                recordUnsupportedCapability: $unsupportedRecorder ?? app(RecordUnsupportedCapability::class),
                advisoryOrchestrator: app(AdvisoryOrchestrator::class),
                recipeDraftPayloadMapper: app(RecipeDraftPayloadMapper::class),
                continuationResolver: app(ContinuationResolver::class),
                pendingClarificationResolver: app(PendingClarificationResolver::class),
                routingDecisionValidator: app(RoutingDecisionValidator::class),
                capabilityFunctionRouter: app(CapabilityFunctionRouter::class),
            ),
        );
    }
}
