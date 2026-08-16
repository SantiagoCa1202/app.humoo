<?php

namespace Tests\Feature\Feature;

use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_tracks_session_and_me_returns_workspace_context(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'password',
            'device_name' => 'humoo-expo-web',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@humoo.local');

        $token = (string) $loginResponse->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseCount('user_sessions', 1);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace.id', $workspace->id)
            ->assertJsonPath('data.current_plan.key', 'pro')
            ->assertJsonFragment([
                'key' => 'active_events',
            ]);

        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'wrong-password',
            'device_name' => 'humoo-expo-web',
        ])->assertUnprocessable();
    }
}
