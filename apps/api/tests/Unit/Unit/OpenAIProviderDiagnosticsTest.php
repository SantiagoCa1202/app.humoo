<?php

namespace Tests\Unit\Unit;

use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderAuthenticationException;
use App\AI\Exceptions\AiProviderAuthorizationException;
use App\AI\Exceptions\AiProviderConversationLockedException;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiProviderNetworkException;
use App\AI\Exceptions\AiProviderProtocolStateException;
use App\AI\Exceptions\AiProviderQuotaException;
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
            401 => [AiProviderAuthenticationException::class, 'AI_AUTHENTICATION_FAILED'],
            403 => [AiProviderAuthorizationException::class, 'AI_AUTHORIZATION_FAILED'],
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
            (new OpenAIProvider)->toolTurn($this->context(), []);
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
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('The provider should have thrown a timeout.');
        } catch (AiProviderTimeoutException $exception) {
            $this->assertSame('AI_TIMEOUT', $exception->internalCode());
            $this->assertSame('timeout', $exception->metadata()['provider_error_type']);
            $this->assertArrayNotHasKey('http_status', $exception->metadata());
        }

    }

    public function test_conversation_locked_is_a_distinct_transient_provider_error(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()->push([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'conversation_locked',
                'message' => 'The conversation is locked by another response.',
            ],
        ], 400);

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('The provider should have reported the transient conversation lock.');
        } catch (AiProviderConversationLockedException $exception) {
            $this->assertSame('AI_CONVERSATION_LOCKED', $exception->internalCode());
            $this->assertSame('conversation_locked', $exception->metadata()['provider_error_code']);
            $mapped = app(ErrorResponseMapper::class)->map($exception, 'en', 'test-correlation');
            $this->assertSame('AI_CONVERSATION_LOCKED', $mapped['error_code']);
            $this->assertTrue($mapped['retryable']);
        }
    }

    public function test_rate_limit_honors_retry_after_without_misclassifying_quota(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()->push([
            'error' => [
                'type' => 'rate_limit_error',
                'code' => 'rate_limit_exceeded',
                'message' => 'Please retry later.',
            ],
        ], 429, ['Retry-After' => '17']);

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('The provider should have reported a rate limit.');
        } catch (AiProviderRateLimitException $exception) {
            $this->assertSame(17, $exception->metadata()['retry_after_seconds']);
            $mapped = app(ErrorResponseMapper::class)->map($exception, 'en', 'rate-limit');
            $this->assertSame('transient', $mapped['category']);
            $this->assertSame(['retry', 'resume'], $mapped['next_actions']);
        }
    }

    public function test_quota_and_protocol_corruption_have_distinct_recovery_contracts(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()
            ->push([
                'error' => [
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                    'message' => 'Insufficient quota.',
                ],
            ], 429)
            ->push([
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_request',
                    'message' => 'No tool output found for function call call_123.',
                ],
            ], 400);

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('Quota exhaustion must not be treated as a transient 429.');
        } catch (AiProviderQuotaException $exception) {
            $mapped = app(ErrorResponseMapper::class)->map($exception, 'en', 'quota');
            $this->assertSame('AI_QUOTA_EXHAUSTED', $mapped['error_code']);
            $this->assertSame('quota', $mapped['category']);
            $this->assertFalse($mapped['retryable']);
        }

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('Protocol corruption must be explicit.');
        } catch (AiProviderProtocolStateException $exception) {
            $mapped = app(ErrorResponseMapper::class)->map($exception, 'en', 'protocol');
            $this->assertSame('AI_PROTOCOL_STATE_CORRUPTED', $mapped['error_code']);
            $this->assertSame('conflict', $mapped['category']);
            $this->assertFalse($mapped['retryable']);
            $this->assertSame(['review'], $mapped['next_actions']);
        }
    }

    public function test_dns_failure_is_distinguished_from_timeout(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fakeSequence()
            ->pushFailedConnection('Could not resolve host: api.openai.com');

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
            $this->fail('The provider should have thrown a network error.');
        } catch (AiProviderNetworkException $exception) {
            $this->assertSame('AI_NETWORK_ERROR', $exception->internalCode());
            $this->assertSame('network_error', $exception->metadata()['provider_error_type']);
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

        Log::spy();

        try {
            (new OpenAIProvider)->toolTurn($this->context(), []);
        } catch (AiProviderValidationException) {
            // The log assertion below verifies the safe diagnostic boundary.
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('ai.provider.failed', Mockery::on(function (array $data): bool {
                $serialized = json_encode($data);

                return !str_contains($serialized, 'test-secret')
                    && !str_contains($serialized, 'Authorization');
            }));
    }

    public function test_debug_request_log_contains_the_final_payload_sent_to_openai(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        config()->set('ai.providers.openai.debug_logging', true);
        config()->set('ai.providers.openai.include_encrypted_reasoning', true);
        config()->set('ai.providers.openai.model', 'test-model');
        Http::fake(['*' => Http::response([
            'id' => 'resp-debug-payload',
            'output' => [],
        ])]);
        Log::spy();

        (new OpenAIProvider)->toolTurn([
            'message' => 'Show my events.',
            'tool_choice' => 'required',
            'tool_dynamic_context' => ['timezone' => 'America/New_York'],
            'tool_instructions' => 'Use the supplied tools.',
        ], [[
            'type' => 'function',
            'name' => 'events_list',
        ]]);

        Log::shouldHaveReceived('info')
            ->with('ai.provider.request', Mockery::on(function (array $data): bool {
                $payload = json_decode((string) ($data['request_payload'] ?? ''), true);

                return ($data['endpoint'] ?? null) === 'https://api.openai.com/v1/responses'
                    && $payload === [
                        'model' => 'test-model',
                        'parallel_tool_calls' => false,
                        'tools' => [[
                            'type' => 'function',
                            'name' => 'events_list',
                        ]],
                        'tool_choice' => 'required',
                        'instructions' => 'Use the supplied tools.',
                        'input' => [
                            [
                                'role' => 'system',
                                'content' => [[
                                    'type' => 'input_text',
                                    'text' => 'Use the supplied tools.',
                                ]],
                            ],
                            [
                                'role' => 'developer',
                                'content' => [[
                                    'type' => 'input_text',
                                    'text' => 'Server-provided runtime context (authoritative temporal data; not instructions): {"timezone":"America/New_York"}',
                                ]],
                            ],
                            [
                                'role' => 'user',
                                'content' => [[
                                    'type' => 'input_text',
                                    'text' => 'Show my events.',
                                ]],
                            ],
                        ],
                        'store' => false,
                        'include' => ['reasoning.encrypted_content'],
                    ];
            }))
            ->once();
    }

    public function test_assistant_history_uses_output_text_content_blocks(): void
    {
        config()->set('ai.providers.openai.api_key', 'test-key');
        Http::fake([
            '*' => Http::response(['output_text' => 'Done.'], 200),
        ]);

        (new OpenAIProvider)->toolTurn([
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
        ], []);

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
