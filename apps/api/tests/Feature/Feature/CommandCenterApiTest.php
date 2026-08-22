<?php

namespace Tests\Feature\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_center_returns_aggregated_task_summary(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/command-center')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'workspace_summary',
                    'task_summary' => [
                        'assigned',
                        'blocked',
                        'cancelled',
                        'done',
                        'in_progress',
                        'overdue',
                        'todo',
                        'total',
                        'unassigned',
                    ],
                ],
            ]);
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-command-center',
        ])->assertOk()->json('token');
    }
}
