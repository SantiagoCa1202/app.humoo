<?php

namespace Tests\Unit\Unit;

use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderValidationException;
use App\AI\Exceptions\AiRuntimeException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ErrorResponseMapperTest extends TestCase
{
    public function test_internal_database_details_never_become_public_error_copy(): void
    {
        $response = (new ErrorResponseMapper)->map(
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
        $response = (new ErrorResponseMapper)->map(
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
        $response = (new ErrorResponseMapper)->forModel(
            new RuntimeException('SQLSTATE[42S02]: mysql select * from users'),
            'en',
            '01J00000000000000000000000'
        );

        $this->assertSame([
            'ok', 'data', 'error', 'signals', 'meta',
        ], array_keys($response));
        $this->assertSame('INTERNAL_ERROR', $response['error']['code']);
        $this->assertTrue($response['signals']['recoverable']);
        $this->assertFalse(str_contains(strtolower($response['meta']['message_for_model']), 'sqlstate'));
        $this->assertFalse(str_contains(strtolower($response['meta']['message_for_model']), 'mysql'));
        $this->assertSame([], $response['data']);
    }

    public function test_model_validation_errors_are_retryable_and_include_safe_missing_fields(): void
    {
        $exception = ValidationException::withMessages([
            'title' => ['The title field is required.'],
            'recipe_reference' => ['A recipe is required.'],
        ]);

        $response = (new ErrorResponseMapper)->forModel(
            $exception,
            'en',
            '01J00000000000000000000000'
        );

        $this->assertTrue($response['signals']['recoverable']);
        $this->assertSame(['title', 'recipe_reference'], $response['data']['missing_fields']);
        $this->assertSame(
            ['The title field is required.'],
            $response['data']['validation_errors']['title']
        );
    }

    public function test_durable_runtime_failures_keep_distinct_recovery_codes(): void
    {
        $mapper = new ErrorResponseMapper;
        $cases = [
            ['RUN_DEADLINE_EXCEEDED', 'run_deadline_exceeded', true, 'transient'],
            ['TOOL_TIMEOUT', 'tool_timeout', true, 'transient'],
            ['WORKFLOW_RETRY_EXHAUSTED', 'workflow_retry_exhausted', true, 'transient'],
            ['RECOVERY_STATE_UNCERTAIN', 'recovery_state_uncertain', false, 'conflict'],
        ];

        foreach ($cases as [$code, $messageKey, $retryable, $category]) {
            $response = $mapper->map(
                new AiRuntimeException($code, $messageKey, $retryable, 'private runtime detail'),
                'en',
                '01J00000000000000000000000',
                ['objective_id' => '01JOBJECTIVE000000000000000'],
            );

            $this->assertSame($code, $response['error_code']);
            $this->assertSame($category, $response['category']);
            $this->assertSame($retryable, $response['retryable']);
            $this->assertTrue($response['preserved_progress']);
            $this->assertStringNotContainsString('private runtime detail', $response['public_message']);
        }
    }

    public function test_scoped_objective_direct_write_points_only_to_the_plan_route(): void
    {
        $response = (new ErrorResponseMapper)->forModel(
            new AiRuntimeException(
                'SCOPED_OBJECTIVE_REQUIRES_PLAN',
                'validation_failed',
                true,
                'private workflow detail',
            ),
            'en',
            '01J00000000000000000000000',
        );

        $this->assertSame('SCOPED_OBJECTIVE_REQUIRES_PLAN', $response['error']['code']);
        $this->assertTrue($response['signals']['recoverable']);
        $this->assertSame(['execution_plans.create'], $response['meta']['allowed_next_actions']);
    }
}
