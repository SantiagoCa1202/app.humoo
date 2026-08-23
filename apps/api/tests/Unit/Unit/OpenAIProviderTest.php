<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    public function test_it_maps_a_mocked_responses_structured_decision(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake([
            'api.openai.com/*' => Http::response([
                'output_text' => json_encode([
                    'intent' => 'create_menu',
                    'slots' => [
                        'event_id' => null,
                        'event_search' => null,
                        'menu_draft' => [
                            'name' => 'Breakfast',
                            'sections' => [],
                            'excluded_items' => [],
                            'requested_guest_count' => null,
                            'source' => ['type' => 'text', 'text' => 'menu'],
                        ],
                        'ordinal' => null,
                        'requested_guest_count' => null,
                        'prep_guest_count' => null,
                    ],
                ], JSON_THROW_ON_ERROR),
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);

        $decision = (new OpenAIProvider)->generate([
            'available_tools' => [],
            'locale' => 'en',
            'message' => 'Create a menu.',
            'system_instructions' => 'Use tools.',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['model'] === 'test-model'
            && $request['text']['format']['type'] === 'json_schema');
        $this->assertSame('create_menu', $decision['intent']);
        $this->assertSame('openai', $decision['provider']);
    }
}
