<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_the_humoo_api_payload(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'app',
                    'environment',
                    'php',
                    'database' => [
                        'driver',
                        'connected',
                    ],
                ],
                'meta' => [
                    'request_id',
                ],
            ]);
    }

    public function test_openai_health_endpoint_is_not_public(): void
    {
        $this->getJson('/api/v1/internal/ai/health')
            ->assertUnauthorized();
    }
}
