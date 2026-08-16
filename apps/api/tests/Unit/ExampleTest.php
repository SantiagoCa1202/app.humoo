<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_runtime_status(): void
    {
        $this->getJson('/api/v1/health')
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
            ]);
    }
}
