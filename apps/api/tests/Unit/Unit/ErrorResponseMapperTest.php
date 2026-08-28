<?php

namespace Tests\Unit\Unit;

use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderValidationException;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseMapperTest extends TestCase
{
    public function test_internal_database_details_never_become_public_error_copy(): void
    {
        $response = (new ErrorResponseMapper())->map(
            new RuntimeException('SQLSTATE[42S02]: mysql host=database select * from users'),
            'en',
            '01J00000000000000000000000'
        );

        $this->assertSame('INTERNAL_ERROR', $response['error_code']);
        $this->assertSame('01J00000000000000000000000', $response['correlation_id']);
        $this->assertFalse(str_contains(strtolower($response['message']), 'sqlstate'));
        $this->assertFalse(str_contains(strtolower($response['message']), 'mysql'));
    }

    public function test_provider_validation_uses_a_public_taxonomy_without_provider_payload(): void
    {
        $response = (new ErrorResponseMapper())->map(
            new AiProviderValidationException('OpenAI rejected schema: internal payload'),
            'es',
            '01J00000000000000000000000'
        );

        $this->assertSame('AI_INVALID_REQUEST', $response['error_code']);
        $this->assertFalse(str_contains(strtolower($response['message']), 'schema'));
        $this->assertFalse(str_contains(strtolower($response['message']), 'openai'));
    }

    public function test_model_error_contract_contains_only_safe_recovery_fields(): void
    {
        $response = (new ErrorResponseMapper())->forModel(
            new RuntimeException('SQLSTATE[42S02]: mysql select * from users'),
            'en',
            '01J00000000000000000000000'
        );

        $this->assertSame([
            'ok', 'code', 'message_for_model', 'retryable', 'allowed_next_actions', 'safe_details',
        ], array_keys($response));
        $this->assertFalse(str_contains(strtolower($response['message_for_model']), 'sqlstate'));
        $this->assertFalse(str_contains(strtolower($response['message_for_model']), 'mysql'));
        $this->assertSame([], $response['safe_details']);
    }
}
