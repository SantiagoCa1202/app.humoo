<?php

namespace App\AI\Providers;

use App\AI\Contracts\StreamingToolCallingProvider;
use App\AI\Contracts\ToolCallingProvider;
use App\AI\Exceptions\AiProviderAuthenticationException;
use App\AI\Exceptions\AiProviderAuthorizationException;
use App\AI\Exceptions\AiProviderConversationLockedException;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiProviderInvalidResponseException;
use App\AI\Exceptions\AiProviderNetworkException;
use App\AI\Exceptions\AiProviderRateLimitException;
use App\AI\Exceptions\AiProviderQuotaException;
use App\AI\Exceptions\AiProviderProtocolStateException;
use App\AI\Exceptions\AiProviderTimeoutException;
use App\AI\Exceptions\AiProviderUnavailableException;
use App\AI\Exceptions\AiProviderValidationException;
use App\AI\Support\Latency;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements StreamingToolCallingProvider, ToolCallingProvider
{
    /**
     * Execute one generic tool-calling turn for the canonical ToolRegistry.
     * The orchestrator owns the loop and backend execution; this class only
     * translates the provider transport.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<int, array<string, mixed>>  $input
     * @return array<string, mixed>
     */
    public function toolTurn(
        array $context,
        array $tools,
        ?string $previousResponseId = null,
        array $input = []
    ): array {
        $apiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $model = (string) config('ai.providers.openai.model', 'gpt-5');
        $startedAt = hrtime(true);

        if ($apiKey === '') {
            throw new AiProviderAuthenticationException(
                'OpenAI credentials are not configured.',
                $this->diagnosticMetadata($model, null, null, 0, 'authentication_error', 'missing_api_key', 'OpenAI credentials are not configured.')
            );
        }

        $conversationId = trim((string) ($context['openai_conversation_id'] ?? ''));
        $persistent = $conversationId !== '';
        $requestPayload = [
            'model' => $model,
            'parallel_tool_calls' => false,
            'tools' => $tools,
            'tool_choice' => $this->toolChoice($context),
            'instructions' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
            'input' => $input !== []
                ? ($persistent
                    ? [...$this->dynamicContextInput($context), ...$input]
                    : [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
                            ]],
                        ],
                        ...$this->dynamicContextInput($context),
                        ...$this->conversationInput($context),
                        ...$input,
                    ])
                : ($persistent
                    ? $this->persistentConversationInput($context)
                    : [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
                            ]],
                        ],
                        ...$this->dynamicContextInput($context),
                        ...$this->conversationInput($context),
                    ]),
        ];

        if ($persistent) {
            $requestPayload['conversation'] = $conversationId;
            $compactThreshold = (int) config('ai.conversations.compact_threshold', 0);
            if ((bool) config('ai.conversations.compaction_enabled', true) && $compactThreshold > 0) {
                $requestPayload['context_management'] = [[
                    'type' => 'compaction',
                    'compact_threshold' => $compactThreshold,
                ]];
            }
            $cacheKey = trim((string) ($context['prompt_cache_key'] ?? config('ai.providers.openai.prompt_cache_key', '')));
            if ($cacheKey !== '') {
                $requestPayload['prompt_cache_key'] = $cacheKey;
                $ttl = trim((string) config('ai.providers.openai.prompt_cache_ttl', ''));
                if ($ttl !== '') {
                    $requestPayload['prompt_cache_options'] = ['mode' => 'implicit', 'ttl' => $ttl];
                }
            }
        } else {
            $requestPayload['store'] = false;
            if ((bool) config('ai.providers.openai.include_encrypted_reasoning', true)) {
                $requestPayload['include'] = ['reasoning.encrypted_content'];
            }
        }

        $endpoint = (string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1/responses');
        $this->logDebugRequest($endpoint, $requestPayload);

        try {
            $response = $this->client($apiKey)->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            $metadata = $this->diagnosticMetadata(
                $model,
                null,
                null,
                $this->elapsedMilliseconds($startedAt),
                $this->isTimeout($exception) ? 'timeout' : 'network_error',
                null,
                $this->safeMessage($exception->getMessage())
            );
            $providerException = $this->isTimeout($exception)
                ? new AiProviderTimeoutException('The OpenAI request timed out.', $metadata, $exception)
                : new AiProviderNetworkException('The OpenAI connection failed.', $metadata, $exception);
            $this->logFailure($providerException);
            throw $providerException;
        }

        if ($response->failed()) {
            $this->logDebugResponse($response, null);
            $exception = $this->exceptionForResponse($response, $model, $this->elapsedMilliseconds($startedAt));
            $this->logFailure($exception);
            throw $exception;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new AiProviderInvalidResponseException(
                'OpenAI returned an invalid tool response.',
                $this->diagnosticMetadata($model, $response->status(), $this->requestId($response), $this->elapsedMilliseconds($startedAt), 'invalid_response', 'invalid_payload', 'The response payload was not an object.')
            );
        }

        $this->logDebugResponse($response, null);
        $this->logSuccess($model, $response, $this->elapsedMilliseconds($startedAt));

        return [
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
            'model' => $model,
            'output' => is_array($payload['output'] ?? null) ? $payload['output'] : [],
            'provider' => 'openai',
            'response_id' => is_string($payload['id'] ?? null) ? $payload['id'] : null,
            'usage' => is_array($payload['usage'] ?? null) ? $payload['usage'] : [],
            'output_text' => $this->extractOutputText($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<int, array<string, mixed>>  $input
     * @param  callable(array<string, mixed>): void  $onEvent
     * @return array<string, mixed>
     */
    public function streamToolTurn(
        array $context,
        array $tools,
        ?string $previousResponseId,
        array $input,
        callable $onEvent,
    ): array {
        $apiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $model = (string) config('ai.providers.openai.model', 'gpt-5');
        $startedAt = hrtime(true);

        if ($apiKey === '') {
            throw new AiProviderAuthenticationException(
                'OpenAI credentials are not configured.',
                $this->diagnosticMetadata($model, null, null, 0, 'authentication_error', 'missing_api_key', 'OpenAI credentials are not configured.')
            );
        }

        $requestPayload = $this->toolTurnRequestPayload($context, $tools, $input);
        $requestPayload['stream'] = true;
        $endpoint = (string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1/responses');
        $this->logDebugRequest($endpoint, $requestPayload);

        try {
            $response = $this->client($apiKey)
                ->withHeaders(['Accept' => 'text/event-stream'])
                ->withOptions(['stream' => true])
                ->post($endpoint, $requestPayload);
        } catch (ConnectionException $exception) {
            $metadata = $this->diagnosticMetadata(
                $model,
                null,
                null,
                $this->elapsedMilliseconds($startedAt),
                $this->isTimeout($exception) ? 'timeout' : 'network_error',
                null,
                $this->safeMessage($exception->getMessage())
            );
            $providerException = $this->isTimeout($exception)
                ? new AiProviderTimeoutException('The OpenAI request timed out.', $metadata, $exception)
                : new AiProviderNetworkException('The OpenAI connection failed.', $metadata, $exception);
            $this->logFailure($providerException);
            throw $providerException;
        }

        if ($response->failed()) {
            $this->logDebugResponse($response, null);
            $exception = $this->exceptionForResponse($response, $model, $this->elapsedMilliseconds($startedAt));
            $this->logFailure($exception);
            throw $exception;
        }

        $payload = $this->consumeResponseStream($response, $onEvent);
        if ($payload === null) {
            throw new AiProviderInvalidResponseException(
                'OpenAI returned an incomplete streamed tool response.',
                $this->diagnosticMetadata($model, $response->status(), $this->requestId($response), $this->elapsedMilliseconds($startedAt), 'invalid_response', 'incomplete_stream', 'The streamed response did not complete.')
            );
        }

        $this->logDebugResponse($response, null);
        $this->logSuccess($model, $response, $this->elapsedMilliseconds($startedAt));

        return [
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
            'model' => $model,
            'output' => is_array($payload['output'] ?? null) ? $payload['output'] : [],
            'provider' => 'openai',
            'response_id' => is_string($payload['id'] ?? null) ? $payload['id'] : null,
            'usage' => is_array($payload['usage'] ?? null) ? $payload['usage'] : [],
            'output_text' => $this->extractOutputText($payload),
        ];
    }

    /** @param array<string, string> $metadata @param array<int, array<string, mixed>> $items */
    public function createConversation(array $metadata = [], array $items = []): string
    {
        $apiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $model = (string) config('ai.providers.openai.model', 'gpt-5');
        if ($apiKey === '') {
            throw new AiProviderAuthenticationException('OpenAI credentials are not configured.', $this->diagnosticMetadata($model, null, null, 0, 'authentication_error', 'missing_api_key', 'OpenAI credentials are not configured.'));
        }

        $startedAt = hrtime(true);
        $payload = array_filter(['metadata' => $metadata, 'items' => $items], static fn (mixed $value): bool => $value !== []);
        $endpoint = (string) config('ai.providers.openai.conversations_base_url', 'https://api.openai.com/v1/conversations');

        try {
            $response = $this->client($apiKey)->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw $this->networkException($exception, $model, $startedAt);
        }
        if ($response->failed()) {
            throw $this->exceptionForResponse($response, $model, $this->elapsedMilliseconds($startedAt));
        }

        $responsePayload = $response->json();
        $conversationId = is_array($responsePayload) && is_string($responsePayload['id'] ?? null)
            ? trim($responsePayload['id'])
            : '';
        if ($conversationId === '') {
            throw new AiProviderInvalidResponseException('OpenAI returned an invalid conversation.', $this->diagnosticMetadata($model, $response->status(), $this->requestId($response), $this->elapsedMilliseconds($startedAt), 'invalid_response', 'missing_conversation_id', 'The conversation response did not contain an id.'));
        }

        Log::info('ai.provider.conversation_created', [
            'http_status' => $response->status(),
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
            'model' => $model,
            'provider' => 'openai',
            'request_id' => $this->requestId($response),
        ]);

        return $conversationId;
    }

    public function deleteConversation(string $conversationId): void
    {
        $apiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $model = (string) config('ai.providers.openai.model', 'gpt-5');
        if ($apiKey === '') {
            throw new AiProviderAuthenticationException('OpenAI credentials are not configured.', $this->diagnosticMetadata($model, null, null, 0, 'authentication_error', 'missing_api_key', 'OpenAI credentials are not configured.'));
        }

        $base = rtrim((string) config('ai.providers.openai.conversations_base_url', 'https://api.openai.com/v1/conversations'), '/');
        try {
            $response = $this->client($apiKey)->delete($base.'/'.rawurlencode($conversationId));
        } catch (ConnectionException $exception) {
            throw $this->networkException($exception, $model, hrtime(true));
        }

        if ($response->failed() && $response->status() !== 404) {
            throw $this->exceptionForResponse($response, $model, 0);
        }
    }

    /** @return array<string, mixed> */
    public function healthCheck(): array
    {
        $apiKey = trim((string) config('ai.providers.openai.api_key', ''));
        $model = (string) config('ai.providers.openai.model', 'gpt-5');
        $startedAt = hrtime(true);

        if ($apiKey === '') {
            $exception = new AiProviderAuthenticationException(
                'OpenAI credentials are not configured.',
                $this->diagnosticMetadata(
                    $model,
                    null,
                    null,
                    $this->elapsedMilliseconds($startedAt),
                    'authentication_error',
                    'missing_api_key',
                    'OpenAI credentials are not configured.'
                )
            );
            $this->logFailure($exception);

            throw $exception;
        }

        try {
            $response = $this->client($apiKey)->post(
                (string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1/responses'),
                [
                    'model' => $model,
                    'store' => false,
                    'input' => [[
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => 'Reply with OK.',
                        ]],
                    ]],
                    'max_output_tokens' => 16,
                ]
            );
        } catch (ConnectionException $exception) {
            $metadata = $this->diagnosticMetadata(
                $model,
                null,
                null,
                $this->elapsedMilliseconds($startedAt),
                $this->isTimeout($exception) ? 'timeout' : 'network_error',
                null,
                $this->safeMessage($exception->getMessage())
            );
            $providerException = $this->isTimeout($exception)
                ? new AiProviderTimeoutException('The OpenAI health check timed out.', $metadata, $exception)
                : new AiProviderNetworkException('The OpenAI health check connection failed.', $metadata, $exception);
            $this->logFailure($providerException);

            throw $providerException;
        }

        if ($response->failed()) {
            $exception = $this->exceptionForResponse(
                $response,
                $model,
                $this->elapsedMilliseconds($startedAt)
            );
            $this->logFailure($exception);

            throw $exception;
        }

        $payload = $response->json();
        $outputText = is_array($payload) ? $this->extractOutputText($payload) : null;

        if ($outputText === null || trim($outputText) === '') {
            $exception = new AiProviderInvalidResponseException(
                'OpenAI health check returned an invalid response.',
                $this->diagnosticMetadata(
                    $model,
                    $response->status(),
                    $this->requestId($response),
                    $this->elapsedMilliseconds($startedAt),
                    'invalid_response',
                    'missing_output',
                    'The health check response did not contain output text.'
                )
            );
            $this->logFailure($exception);

            throw $exception;
        }

        $latency = $this->elapsedMilliseconds($startedAt);
        $requestId = $this->requestId($response);
        Log::info('ai.provider.health_check.completed', [
            'exception_class' => null,
            'http_status' => $response->status(),
            'latency_ms' => $latency,
            'model' => $model,
            'provider' => 'openai',
            'provider_error_code' => null,
            'provider_error_type' => null,
            'request_id' => $requestId,
        ]);

        return [
            'authentication_valid' => true,
            'http_status' => $response->status(),
            'latency_ms' => $latency,
            'model' => $model,
            'model_reachable' => true,
            'provider' => 'openai',
            'provider_reachable' => true,
            'request_id' => $requestId,
            'response_valid' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<int, array<string, mixed>>  $input
     * @return array<string, mixed>
     */
    private function toolTurnRequestPayload(array $context, array $tools, array $input): array
    {
        $conversationId = trim((string) ($context['openai_conversation_id'] ?? ''));
        $persistent = $conversationId !== '';
        $requestPayload = [
            'model' => (string) config('ai.providers.openai.model', 'gpt-5'),
            'parallel_tool_calls' => false,
            'tools' => $tools,
            'tool_choice' => $this->toolChoice($context),
            'instructions' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
            'input' => $input !== []
                ? ($persistent
                    ? [...$this->dynamicContextInput($context), ...$input]
                    : [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
                            ]],
                        ],
                        ...$this->dynamicContextInput($context),
                        ...$this->conversationInput($context),
                        ...$input,
                    ])
                : ($persistent
                    ? $this->persistentConversationInput($context)
                    : [
                        [
                            'role' => 'system',
                            'content' => [[
                                'type' => 'input_text',
                                'text' => (string) ($context['tool_instructions'] ?? $context['function_instructions'] ?? ''),
                            ]],
                        ],
                        ...$this->dynamicContextInput($context),
                        ...$this->conversationInput($context),
                    ]),
        ];

        if ($persistent) {
            $requestPayload['conversation'] = $conversationId;
            $compactThreshold = (int) config('ai.conversations.compact_threshold', 0);
            if ((bool) config('ai.conversations.compaction_enabled', true) && $compactThreshold > 0) {
                $requestPayload['context_management'] = [[
                    'type' => 'compaction',
                    'compact_threshold' => $compactThreshold,
                ]];
            }
            $cacheKey = trim((string) ($context['prompt_cache_key'] ?? config('ai.providers.openai.prompt_cache_key', '')));
            if ($cacheKey !== '') {
                $requestPayload['prompt_cache_key'] = $cacheKey;
                $ttl = trim((string) config('ai.providers.openai.prompt_cache_ttl', ''));
                if ($ttl !== '') {
                    $requestPayload['prompt_cache_options'] = ['mode' => 'implicit', 'ttl' => $ttl];
                }
            }
        } else {
            $requestPayload['store'] = false;
            if ((bool) config('ai.providers.openai.include_encrypted_reasoning', true)) {
                $requestPayload['include'] = ['reasoning.encrypted_content'];
            }
        }

        return $requestPayload;
    }

    /** @param array<string, mixed> $context */
    private function toolChoice(array $context): string
    {
        $choice = trim((string) ($context['tool_choice'] ?? 'auto'));

        return in_array($choice, ['auto', 'required', 'none'], true) ? $choice : 'auto';
    }

    /**
     * @param  callable(array<string, mixed>): void  $onEvent
     * @return array<string, mixed>|null
     */
    private function consumeResponseStream(Response $response, callable $onEvent): ?array
    {
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $completedPayload = null;

        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;
            $buffer = str_replace("\r\n", "\n", $buffer);
            while (($boundary = strpos($buffer, "\n\n")) !== false) {
                $frame = substr($buffer, 0, $boundary);
                $buffer = substr($buffer, $boundary + 2);
                $payload = $this->streamFramePayload($frame);
                if ($payload === null) {
                    continue;
                }

                if (($payload['type'] ?? null) === 'response.output_text.delta' && is_string($payload['delta'] ?? null)) {
                    $onEvent(['delta' => $payload['delta'], 'type' => 'output_text.delta']);

                    continue;
                }

                if (($payload['type'] ?? null) === 'response.completed' && is_array($payload['response'] ?? null)) {
                    $completedPayload = $payload['response'];
                }
            }
        }

        if ($buffer !== '') {
            $payload = $this->streamFramePayload($buffer);
            if (($payload['type'] ?? null) === 'response.completed' && is_array($payload['response'] ?? null)) {
                $completedPayload = $payload['response'];
            }
        }

        return $completedPayload;
    }

    /** @return array<string, mixed>|null */
    private function streamFramePayload(string $frame): ?array
    {
        $data = [];
        foreach (explode("\n", $frame) as $line) {
            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5));
            }
        }

        if ($data === []) {
            return null;
        }

        $payload = json_decode(implode("\n", $data), true);

        return is_array($payload) ? $payload : null;
    }

    private function client(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(max(1, (int) config('ai.providers.openai.connect_timeout_seconds', 10)))
            ->timeout(max(5, (int) config('ai.providers.openai.timeout_seconds', 30)));
    }

    private function exceptionForResponse(
        Response $response,
        string $model,
        int $latency
    ): AiProviderException {
        $payload = $response->json();
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
        $status = $response->status();
        $metadata = $this->diagnosticMetadata(
            $model,
            $status,
            $this->requestId($response),
            $latency,
            $this->safeString($error['type'] ?? null) ?? 'http_error',
            $this->safeString($error['code'] ?? null),
            $this->safeMessage((string) ($error['message'] ?? '')) ?? 'OpenAI returned an HTTP error.'
        );
        $retryAfter = $this->retryAfterSeconds($response);
        if ($retryAfter !== null) {
            $metadata['retry_after_seconds'] = $retryAfter;
        }
        $providerCode = strtolower(trim((string) ($error['code'] ?? '')));
        $providerMessage = strtolower(trim((string) ($error['message'] ?? '')));
        $quotaExhausted = in_array($providerCode, ['insufficient_quota', 'billing_hard_limit_reached', 'billing_limit_reached'], true)
            || str_contains($providerMessage, 'billing hard limit')
            || str_contains($providerMessage, 'insufficient quota');
        $protocolCorrupted = str_contains($providerMessage, 'no tool output found for function call');

        return match (true) {
            $status === 401 => new AiProviderAuthenticationException('OpenAI authentication failed.', $metadata),
            $status === 403 => new AiProviderAuthorizationException('OpenAI authorization failed.', $metadata),
            $status === 408 => new AiProviderTimeoutException('OpenAI request timed out.', $metadata),
            $quotaExhausted => new AiProviderQuotaException('OpenAI quota is exhausted.', $metadata),
            $status === 429 => new AiProviderRateLimitException('OpenAI rate limit was reached.', $metadata),
            $status === 404 => new AiProviderUnavailableException('OpenAI endpoint or model was not found.', $metadata),
            $this->isConversationLocked($error) => new AiProviderConversationLockedException('The OpenAI conversation is temporarily locked.', $metadata),
            $protocolCorrupted => new AiProviderProtocolStateException('The OpenAI conversation protocol state is incomplete.', $metadata),
            $status === 400 || $status === 422 || ($status >= 400 && $status < 500) => new AiProviderValidationException('OpenAI rejected the request.', $metadata),
            default => new AiProviderUnavailableException('OpenAI is temporarily unavailable.', $metadata),
        };
    }

    /** @param array<string, mixed> $error */
    private function isConversationLocked(array $error): bool
    {
        $code = strtolower(trim((string) ($error['code'] ?? '')));
        $type = strtolower(trim((string) ($error['type'] ?? '')));
        $message = strtolower(trim((string) ($error['message'] ?? '')));

        return $code === 'conversation_locked'
            || $type === 'conversation_locked'
            || str_contains($message, 'conversation is locked')
            || str_contains($message, 'conversation_locked');
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $value = trim((string) $response->header('Retry-After'));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return max(0, (int) ceil((float) $value));
        }
        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }

    private function extractOutputText(array $payload): ?string
    {
        if (is_string($payload['output_text'] ?? null)) {
            return $payload['output_text'];
        }

        foreach ($payload['output'] ?? [] as $output) {
            if (! is_array($output)) {
                continue;
            }

            foreach ($output['content'] ?? [] as $content) {
                if (
                    is_array($content)
                    && ($content['type'] ?? null) === 'output_text'
                    && is_string($content['text'] ?? null)
                ) {
                    return $content['text'];
                }
            }
        }

        return null;
    }

    private function diagnosticMetadata(
        string $model,
        ?int $httpStatus,
        ?string $requestId,
        int $latency,
        ?string $providerErrorType,
        ?string $providerErrorCode,
        ?string $providerMessage
    ): array {
        return array_filter([
            'http_status' => $httpStatus,
            'latency_ms' => $latency,
            'model' => $model,
            'provider' => 'openai',
            'provider_error_code' => $providerErrorCode,
            'provider_error_type' => $providerErrorType,
            'provider_message' => $providerMessage,
            'request_id' => $requestId,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function requestId(Response $response): ?string
    {
        return $this->safeString($response->header('x-request-id'));
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return Latency::fromNanoseconds($startedAt, hrtime(true));
    }

    private function isTimeout(ConnectionException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'timeout')
            || str_contains(strtolower($exception->getMessage()), 'timed out');
    }

    private function safeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        return substr(trim((string) $value), 0, 500);
    }

    private function safeMessage(string $message): ?string
    {
        $message = preg_replace('/Bearer\\s+\\S+/i', 'Bearer [REDACTED]', $message) ?? $message;
        $message = preg_replace('/sk-[A-Za-z0-9_-]+/i', '[REDACTED]', $message) ?? $message;

        return $this->safeString($message);
    }

    private function logFailure(AiProviderException $exception): void
    {
        $metadata = $exception->metadata();

        Log::warning('ai.provider.failed', [
            ...$metadata,
            'exception_class' => class_basename($exception),
            'http_status' => $metadata['http_status'] ?? null,
            'internal_code' => $exception->internalCode(),
            'latency_ms' => $metadata['latency_ms'] ?? null,
            'model' => $metadata['model'] ?? null,
            'provider' => $metadata['provider'] ?? 'openai',
            'provider_error_code' => $metadata['provider_error_code'] ?? null,
            'provider_error_type' => $metadata['provider_error_type'] ?? null,
            'request_id' => $metadata['request_id'] ?? null,
        ]);

        $classifiedEvent = match ($exception->internalCode()) {
            'AI_RATE_LIMITED' => 'provider.rate_limited',
            'AI_QUOTA_EXHAUSTED', 'AI_BILLING_LIMIT_REACHED' => 'provider.quota_exhausted',
            'AI_TIMEOUT' => 'provider.timeout',
            'AI_CONVERSATION_LOCKED' => 'provider.conversation_locked',
            default => null,
        };
        if ($classifiedEvent !== null) {
            Log::warning($classifiedEvent, [
                'http_status' => $metadata['http_status'] ?? null,
                'internal_code' => $exception->internalCode(),
                'provider' => $metadata['provider'] ?? 'openai',
                'request_id' => $metadata['request_id'] ?? null,
                'retry_after_seconds' => $metadata['retry_after_seconds'] ?? null,
            ]);
        }
    }

    private function logSuccess(string $model, Response $response, int $latency): void
    {
        Log::info('ai.provider.completed', [
            'exception_class' => null,
            'http_status' => $response->status(),
            'latency_ms' => $latency,
            'model' => $model,
            'provider' => 'openai',
            'provider_error_code' => null,
            'provider_error_type' => null,
            'request_id' => $this->requestId($response),
        ]);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     */
    private function logDebugRequest(string $endpoint, array $requestPayload): void
    {
        if (! config('ai.providers.openai.debug_logging', false)) {
            return;
        }

        $tools = collect($requestPayload['tools'] ?? [])->filter(fn (mixed $tool): bool => is_array($tool));
        $declaredTools = $tools->flatMap(function (array $tool): array {
            if (($tool['type'] ?? null) === 'namespace') {
                return collect($tool['tools'] ?? [])->pluck('name')->filter()->values()->all();
            }

            return filled($tool['name'] ?? null) ? [(string) $tool['name']] : [];
        })->values()->all();
        $serializedPayload = json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Log::info('ai.provider.request', [
            'endpoint' => $endpoint,
            'model' => $requestPayload['model'] ?? null,
            'request_payload' => $serializedPayload === false ? '{}' : $serializedPayload,
            'declared_tools' => $declaredTools,
            'deferred_tools' => $tools
                ->flatMap(fn (array $tool): array => ($tool['type'] ?? null) === 'namespace' ? (array) ($tool['tools'] ?? []) : [$tool])
                ->filter(fn (mixed $tool): bool => is_array($tool) && (bool) ($tool['defer_loading'] ?? false))
                ->pluck('name')
                ->filter()
                ->values()
                ->all(),
            'tool_names' => $declaredTools,
            'visible_namespaces' => $tools->where('type', 'namespace')->pluck('name')->filter()->values()->all(),
            'input_message_count' => count($requestPayload['input'] ?? []),
            'input_character_count' => collect($requestPayload['input'] ?? [])
                ->flatMap(fn (mixed $message): array => is_array($message) ? ($message['content'] ?? []) : [])
                ->sum(fn (mixed $content): int => is_array($content) ? strlen((string) ($content['text'] ?? '')) : 0),
        ]);
    }

    private function logDebugResponse(Response $response, ?string $outputText): void
    {
        if (! config('ai.providers.openai.debug_logging', false)) {
            return;
        }

        Log::info('ai.provider.response', [
            'http_status' => $response->status(),
            'request_id' => $this->requestId($response),
        ]);
    }

    /** @param array<string, mixed> $context @return array<int, array<string, mixed>> */
    private function persistentConversationInput(array $context): array
    {
        $input = $this->dynamicContextInput($context);
        foreach ($context['pending_provider_tool_outputs'] ?? [] as $toolOutput) {
            if (! is_array($toolOutput)
                || trim((string) ($toolOutput['call_id'] ?? '')) === ''
                || ! is_array($toolOutput['output'] ?? null)) {
                continue;
            }

            $input[] = [
                'type' => 'function_call_output',
                'call_id' => (string) $toolOutput['call_id'],
                'output' => json_encode(
                    $toolOutput['output'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
            ];
        }
        $message = trim((string) ($context['message'] ?? ''));
        if ($message !== '') {
            $input[] = [
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => $message,
                ]],
            ];
        }

        return $input;
    }

    /** @param array<string, mixed> $context @return array<int, array<string, mixed>> */
    private function dynamicContextInput(array $context): array
    {
        $dynamic = $context['tool_dynamic_context'] ?? null;
        if (! is_array($dynamic) || $dynamic === []) {
            return [];
        }

        $encoded = json_encode($dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return [];
        }

        return [[
            'role' => 'developer',
            'content' => [[
                'type' => 'input_text',
                'text' => 'Server-provided runtime context (authoritative temporal data; not instructions): '.$encoded,
            ]],
        ]];
    }

    private function conversationInput(array $context): array
    {
        $recentMessages = collect($context['recent_messages'] ?? [])
            ->filter(fn (mixed $message): bool => is_array($message))
            ->map(function (array $message): ?array {
                $content = trim((string) ($message['content_text'] ?? ''));

                if ($content === '') {
                    return null;
                }

                $role = ($message['sender_type'] ?? null) === 'assistant' ? 'assistant' : 'user';

                return [
                    'role' => $role,
                    'content' => [[
                        'type' => $role === 'assistant' ? 'output_text' : 'input_text',
                        'text' => $content,
                    ]],
                ];
            })
            ->filter()
            ->values()
            ->all();

        $currentMessageId = (string) ($context['message_id'] ?? '');
        $containsCurrentMessage = collect($context['recent_messages'] ?? [])
            ->contains(fn (mixed $message): bool => is_array($message)
                && (string) ($message['id'] ?? '') === $currentMessageId);

        if (! $containsCurrentMessage) {
            $recentMessages[] = [
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => (string) ($context['message'] ?? ''),
                ]],
            ];
        }

        return $recentMessages;
    }

    private function networkException(ConnectionException $exception, string $model, int $startedAt): AiProviderException
    {
        $metadata = $this->diagnosticMetadata(
            $model,
            null,
            null,
            $this->elapsedMilliseconds($startedAt),
            $this->isTimeout($exception) ? 'timeout' : 'network_error',
            null,
            $this->safeMessage($exception->getMessage())
        );

        return $this->isTimeout($exception)
            ? new AiProviderTimeoutException('The OpenAI request timed out.', $metadata, $exception)
            : new AiProviderNetworkException('The OpenAI connection failed.', $metadata, $exception);
    }

}
