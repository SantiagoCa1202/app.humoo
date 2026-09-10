<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function test_tool_turn_sends_hosted_tool_search_with_deferred_functions(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/*' => Http::response([
            'id' => 'resp-discovery',
            'output' => [[
                'type' => 'function_call',
                'name' => 'recipes_list',
                'call_id' => 'call-recipes-list',
                'arguments' => '{"search":"Ranch"}',
            ]],
        ])]);

        (new OpenAIProvider)->toolTurn([
            'message' => 'Busca Ranch.',
            'tool_choice' => 'required',
            'tool_instructions' => 'Discover and use tools.',
        ], [
            [
                'type' => 'function',
                'name' => 'recipes_list',
                'description' => 'Search recipes.',
                'strict' => true,
                'defer_loading' => true,
                'parameters' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            ['type' => 'tool_search'],
        ]);

        Http::assertSent(fn (Request $request): bool => $request['tools'][0]['defer_loading'] === true
            && $request['tools'][1]['type'] === 'tool_search'
            && $request['tool_choice'] === 'required');
    }

    public function test_it_maps_a_generic_tool_turn_and_continues_statelessly_from_a_previous_response(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push([
                    'id' => 'resp-search',
                    'output' => [[
                        'type' => 'function_call',
                        'name' => 'recipes_list',
                        'call_id' => 'call-search',
                        'arguments' => '{"search":"baguette"}',
                    ]],
                    'usage' => ['input_tokens' => 11, 'output_tokens' => 7],
                ])
                ->push([
                    'id' => 'resp-final',
                    'output' => [[
                        'type' => 'message',
                        'content' => [['type' => 'output_text', 'text' => 'Encontré la receta.']],
                    ]],
                    'output_text' => 'Encontré la receta.',
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 4],
                ]),
        ]);

        $provider = new OpenAIProvider;
        $tools = [[
            'type' => 'function',
            'name' => 'recipes_list',
            'description' => 'Search recipes.',
            'strict' => true,
            'parameters' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['search'],
                'properties' => ['search' => ['type' => ['string', 'null']]],
            ],
        ]];

        $first = $provider->toolTurn([
            'message' => 'Find the baguette recipe.',
            'message_id' => 'message-1',
            'recent_messages' => [['id' => 'message-1', 'content_text' => 'Find the baguette recipe.', 'sender_type' => 'user']],
            'tool_choice' => 'required',
            'tool_instructions' => 'Use tools.',
        ], $tools);
        $second = $provider->toolTurn(
            ['tool_choice' => 'required', 'tool_instructions' => 'Use the result.'],
            $tools,
            $first['response_id'],
            [
                [
                    'type' => 'function_call',
                    'name' => 'recipes_list',
                    'call_id' => 'call-search',
                    'arguments' => '{"search":"baguette"}',
                ],
                ['type' => 'function_call_output', 'call_id' => 'call-search', 'output' => '{"ok":true}'],
            ]
        );

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ! isset($request['previous_response_id'])
            && $request['store'] === false
            && $request['tool_choice'] === 'required'
            && $request['include'] === ['reasoning.encrypted_content']
            && isset($request['input'][2], $request['input'][3])
            && $request['input'][2]['type'] === 'function_call'
            && $request['input'][3]['type'] === 'function_call_output');
        $this->assertSame('resp-final', $second['response_id']);
        $this->assertSame('Encontré la receta.', $second['output_text']);
        $this->assertSame(11, $first['usage']['input_tokens']);
    }

    public function test_it_uses_a_durable_conversation_without_replaying_history_or_reasoning(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake(['api.openai.com/*' => Http::response([
            'id' => 'resp-persistent',
            'conversation' => 'conv_123',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'Contexto persistente.']],
            ]],
            'output_text' => 'Contexto persistente.',
            'usage' => [
                'input_tokens' => 20,
                'input_tokens_details' => ['cached_tokens' => 12],
                'output_tokens' => 4,
            ],
        ])]);

        $result = (new OpenAIProvider)->toolTurn([
            'openai_conversation_id' => 'conv_123',
            'message' => 'Show the same recipe.',
            'recent_messages' => [
                ['id' => 'old', 'content_text' => 'A long previous turn.', 'sender_type' => 'user'],
                ['id' => 'current', 'content_text' => 'Show the same recipe.', 'sender_type' => 'user'],
            ],
            'tool_instructions' => 'Stable instructions.',
            'tool_dynamic_context' => ['operational_context' => ['active_entity_refs' => []]],
            'prompt_cache_key' => 'humoo-agent-v1:recipes',
        ], []);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['conversation'] === 'conv_123'
            && ! isset($request['store'])
            && ! isset($request['include'])
            && ! isset($request['previous_response_id'])
            && $request['instructions'] === 'Stable instructions.'
            && $request['prompt_cache_key'] === 'humoo-agent-v1:recipes'
            && count($request['input']) === 2
            && $request['input'][0]['role'] === 'developer'
            && $request['input'][1]['content'][0]['text'] === 'Show the same recipe.');
        $this->assertSame('resp-persistent', $result['response_id']);
        $this->assertSame(12, $result['usage']['input_tokens_details']['cached_tokens']);
    }

    public function test_it_closes_a_pending_provider_tool_call_before_the_next_user_message(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/*' => Http::response([
            'id' => 'resp-next',
            'conversation' => 'conv_123',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'Nuevo contexto.']],
            ]],
            'output_text' => 'Nuevo contexto.',
        ])]);

        (new OpenAIProvider)->toolTurn([
            'openai_conversation_id' => 'conv_123',
            'message' => 'Muéstrame mis eventos de mañana.',
            'tool_instructions' => 'Use tools.',
            'pending_provider_tool_outputs' => [[
                'call_id' => 'call-create-task',
                'output' => [
                    'ok' => false,
                    'data' => ['action_key' => 'tasks.create', 'status' => 'cancelled'],
                    'error' => ['code' => 'TOOL_CANCELLED', 'message' => 'The tool was not executed.'],
                    'signals' => ['recoverable' => false],
                    'meta' => ['message_for_model' => 'The tool was not executed.', 'allowed_next_actions' => []],
                ],
            ]],
        ], []);

        Http::assertSent(fn (Request $request): bool => $request['conversation'] === 'conv_123'
            && count($request['input']) === 2
            && $request['input'][0]['type'] === 'function_call_output'
            && $request['input'][0]['call_id'] === 'call-create-task'
            && str_contains($request['input'][0]['output'], 'TOOL_CANCELLED')
            && $request['input'][1]['role'] === 'user'
            && $request['input'][1]['content'][0]['text'] === 'Muéstrame mis eventos de mañana.');
    }

    public function test_it_creates_and_deletes_a_conversation_through_the_conversations_api(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake([
            'api.openai.com/v1/conversations' => Http::response(['id' => 'conv_123'], 200),
            'api.openai.com/v1/conversations/conv_123' => Http::response(['deleted' => true], 200),
        ]);

        $provider = new OpenAIProvider;
        $this->assertSame('conv_123', $provider->createConversation(
            ['humoo_conversation_id' => 'local-1'],
            [['type' => 'message', 'role' => 'user', 'content' => 'Previous turn.']]
        ));
        $provider->deleteConversation('conv_123');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.openai.com/v1/conversations'
            && $request['items'][0]['type'] === 'message');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://api.openai.com/v1/conversations/conv_123');
    }

    public function test_temporal_context_is_dynamic_input_and_never_part_of_instructions(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fakeSequence('api.openai.com/v1/responses')
            ->push([
                'id' => 'resp-tool',
                'output' => [[
                    'type' => 'function_call',
                    'name' => 'tasks_create',
                    'call_id' => 'call-1',
                    'arguments' => '{}',
                ]],
            ])
            ->push([
                'id' => 'resp-final',
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'Listo.']],
                ]],
                'output_text' => 'Listo.',
            ]);

        $context = [
            'openai_conversation_id' => 'conv_123',
            'message' => 'Crea una tarea mañana a las 9 AM.',
            'recent_messages' => [],
            'tool_instructions' => 'Stable instructions.',
            'tool_dynamic_context' => [
                'temporal' => [
                    'current_local_date' => '2026-08-29',
                    'current_local_time' => '15:46',
                    'timezone' => 'America/New_York',
                ],
            ],
        ];

        (new OpenAIProvider)->toolTurn($context, []);
        (new OpenAIProvider)->toolTurn($context, [], 'resp-tool', [[
            'type' => 'function_call_output',
            'call_id' => 'call-1',
            'output' => '{"ok":true}',
        ]]);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $dynamic = collect($request['input'] ?? [])->first(fn (mixed $item): bool => is_array($item)
                && ($item['role'] ?? null) === 'developer');
            $text = is_array($dynamic) ? (string) data_get($dynamic, 'content.0.text') : '';

            return ! str_contains((string) ($request['instructions'] ?? ''), '2026-08-29')
                && str_contains($text, '2026-08-29')
                && str_contains($text, 'America/New_York');
        });
    }
}
