<?php

namespace Tests\Unit\Unit;

use App\AI\Exceptions\AiProviderAuthenticationException;
use App\AI\Exceptions\AiProviderAuthorizationException;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiProviderInvalidResponseException;
use App\AI\Exceptions\AiProviderNetworkException;
use App\AI\Exceptions\AiProviderRateLimitException;
use App\AI\Exceptions\AiProviderTimeoutException;
use App\AI\Exceptions\AiProviderUnavailableException;
use App\AI\Exceptions\AiProviderValidationException;
use App\AI\Providers\OpenAIProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class OpenAIProviderDiagnosticsTest extends TestCase
{
    public function test_http_errors_keep_safe_provider_diagnostics_and_internal_codes(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');

        $cases = [
            400 => [AiProviderValidationException::class, 'AI_BAD_REQUEST'],
            401 => [AiProviderAuthenticationException::class, 'AI_AUTH_ERROR'],
            403 => [AiProviderAuthorizationException::class, 'AI_AUTH_ERROR'],
            404 => [AiProviderUnavailableException::class, 'AI_PROVIDER_UNAVAILABLE'],
            408 => [AiProviderTimeoutException::class, 'AI_TIMEOUT'],
            422 => [AiProviderValidationException::class, 'AI_BAD_REQUEST'],
            429 => [AiProviderRateLimitException::class, 'AI_RATE_LIMITED'],
            500 => [AiProviderUnavailableException::class, 'AI_PROVIDER_UNAVAILABLE'],
        ];

        $sequence = Http::fakeSequence();
        foreach ($cases as $status => [$expectedException, $expectedCode]) {
            $sequence->push([
                    'error' => [
                        'type' => 'provider_error',
                        'code' => 'test_error_code',
                        'message' => 'A safe provider error message.',
                    ],
                ], $status, ['x-request-id' => 'req-test-'.$status]);
        }

        foreach ($cases as $status => [$expectedException, $expectedCode]) {
            try {
                (new OpenAIProvider)->generate($this->context());
                $this->fail('The provider should have thrown for HTTP '.$status.'.');
            } catch (AiProviderException $exception) {
                $this->assertInstanceOf($expectedException, $exception);
                $this->assertSame($expectedCode, $exception->internalCode());
                $this->assertSame($status, $exception->metadata()['http_status']);
                $this->assertSame('provider_error', $exception->metadata()['provider_error_type']);
                $this->assertSame('test_error_code', $exception->metadata()['provider_error_code']);
                $this->assertSame('req-test-'.$status, $exception->metadata()['request_id']);
                $this->assertIsInt($exception->metadata()['latency_ms']);
            }
        }
    }

    public function test_timeout_is_distinguished_from_http_errors(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');

        Http::fakeSequence()
            ->pushFailedConnection('cURL error 28: Operation timed out');

        try {
            (new OpenAIProvider)->generate($this->context());
            $this->fail('The provider should have thrown a timeout.');
        } catch (AiProviderTimeoutException $exception) {
            $this->assertSame('AI_TIMEOUT', $exception->internalCode());
            $this->assertSame('timeout', $exception->metadata()['provider_error_type']);
            $this->assertArrayNotHasKey('http_status', $exception->metadata());
        }

    }

    public function test_dns_failure_is_distinguished_from_timeout(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()
            ->pushFailedConnection('Could not resolve host: api.openai.com');

        try {
            (new OpenAIProvider)->generate($this->context());
            $this->fail('The provider should have thrown a network error.');
        } catch (AiProviderNetworkException $exception) {
            $this->assertSame('AI_NETWORK_ERROR', $exception->internalCode());
            $this->assertSame('network_error', $exception->metadata()['provider_error_type']);
        }
    }

    public function test_invalid_structured_output_is_not_reported_as_provider_unavailable(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()
            ->push([
                'output_text' => '{not-json',
            ], 200, ['x-request-id' => 'req-invalid'])
            ->push(['output_text' => '{}'], 200);

        try {
            (new OpenAIProvider)->generate($this->context());
            $this->fail('The provider should have thrown for invalid structured output.');
        } catch (AiProviderInvalidResponseException $exception) {
            $this->assertSame('AI_INVALID_RESPONSE', $exception->internalCode());
            $this->assertSame('structured_output_invalid', $exception->metadata()['provider_error_type']);
            $this->assertSame('invalid_json', $exception->metadata()['provider_error_code']);
            $this->assertSame('req-invalid', $exception->metadata()['request_id']);
        }

        try {
            (new OpenAIProvider)->generate($this->context());
            $this->fail('The provider should have thrown for an invalid decision schema.');
        } catch (AiProviderInvalidResponseException $exception) {
            $this->assertSame('invalid_decision_schema', $exception->metadata()['provider_error_code']);
        }
    }

    public function test_provider_logs_do_not_include_secrets(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake(static function () {
            return Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => 'Bearer test-secret sk-test-secret',
                ],
            ], 400);
        });

        Log::shouldReceive('warning')
            ->once()
            ->with('ai.provider.failed', Mockery::on(function (array $data): bool {
                $serialized = json_encode($data);

                return !str_contains($serialized, 'test-secret')
                    && !str_contains($serialized, 'Authorization');
            }));

        try {
            (new OpenAIProvider)->generate($this->context());
        } catch (AiProviderValidationException) {
            // The log assertion below verifies the safe diagnostic boundary.
        }

    }

    public function test_assistant_history_uses_output_text_content_blocks(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake([
            '*' => Http::response([
                'output_text' => json_encode([
                    'intent' => 'show_events',
                    'slots' => [],
                ]),
            ], 200),
        ]);

        (new OpenAIProvider)->generate([
            ...$this->context(),
            'recent_messages' => [
                [
                    'content_text' => 'Show my events.',
                    'sender_type' => 'user',
                ],
                [
                    'content_text' => 'I will check your events.',
                    'sender_type' => 'assistant',
                ],
            ],
        ]);

        Http::assertSent(function (Request $request): bool {
            $input = $request['input'];

            return $input[1]['role'] === 'user'
                && $input[1]['content'][0]['type'] === 'input_text'
                && $input[2]['role'] === 'assistant'
                && $input[2]['content'][0]['type'] === 'output_text';
        });
    }

    public function test_health_check_uses_a_minimal_responses_request(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake([
            '*' => Http::response([
                'output_text' => 'OK',
            ], 200, ['x-request-id' => 'req-health']),
        ]);

        $health = (new OpenAIProvider)->healthCheck();

        $this->assertTrue($health['provider_reachable']);
        $this->assertTrue($health['authentication_valid']);
        $this->assertTrue($health['model_reachable']);
        $this->assertTrue($health['response_valid']);
        $this->assertSame('req-health', $health['request_id']);
        Http::assertSent(function (Request $request): bool {
            return $request['model'] === 'test-model'
                && $request['store'] === false
                && $request['max_output_tokens'] === 16
                && $request['input'][0]['content'][0]['text'] === 'Reply with OK.';
        });
    }

    private function context(): array
    {
        return [
            'available_tools' => [],
            'locale' => 'en',
            'message' => 'Show my events.',
            'message_id' => 'message-id',
            'recent_messages' => [],
            'system_instructions' => 'Use tools.',
        ];
    }
}
