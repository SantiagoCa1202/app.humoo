<?php

namespace Tests\Feature\Feature;

use App\Models\UserSession;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace.id', $workspace->id)
            ->assertJsonFragment([
                'email' => 'owner@humoo.local',
            ]);

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

    public function test_auth_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
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

    public function test_email_registration_creates_user_and_api_session(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Jennifer',
            'last_name' => 'Rivera',
            'email' => 'jennifer@humoo.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'humoo-expo-web',
            'locale' => 'es',
            'timezone' => 'America/New_York',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.email', 'jennifer@humoo.local');

        $token = (string) $response->json('token');

        $this->assertDatabaseHas('users', [
            'email' => 'jennifer@humoo.local',
            'status' => 'active',
            'locale' => 'es',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace', null)
            ->assertJsonPath('data.memberships', []);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('user_sessions', 1);
    }

    public function test_forgot_and_reset_password_rotate_credentials_and_revoke_existing_sessions(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'password',
            'device_name' => 'humoo-expo-web',
        ])->assertOk();

        $currentToken = (string) $loginResponse->json('token');

        $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'owner@humoo.local',
        ]);

        $forgotResponse
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@humoo.local');

        $resetToken = (string) $forgotResponse->json('data.reset_token_preview');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'owner@humoo.local',
            'token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->withToken($currentToken)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->assertSame(
            0,
            UserSession::query()
                ->whereNull('revoked_at')
                ->count(),
        );

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'password',
            'device_name' => 'humoo-expo-web',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'new-password-123',
            'device_name' => 'humoo-expo-web',
        ])->assertOk();
    }

    public function test_logout_revokes_current_token_and_session(): void
    {
        $this->seed(DatabaseSeeder::class);

        $token = $this->login('owner@humoo.local', 'password', 'humoo-expo-web');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(
            0,
            UserSession::query()
                ->whereNull('revoked_at')
                ->count(),
        );

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_login_is_rate_limited_after_five_attempts_per_minute(): void
    {
        $this->seed(DatabaseSeeder::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'owner@humoo.local',
                'password' => 'wrong-password',
                'device_name' => 'humoo-expo-web',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@humoo.local',
            'password' => 'wrong-password',
            'device_name' => 'humoo-expo-web',
        ])->assertStatus(429);
    }

    public function test_user_can_manually_revoke_another_session(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tokenA = $this->login('owner@humoo.local', 'password', 'humoo-expo-web');
        $tokenB = $this->login('owner@humoo.local', 'password', 'humoo-expo-ios');

        $secondarySession = UserSession::query()
            ->where('token_id', Str::before($tokenB, '|'))
            ->firstOrFail();

        $this->withToken($tokenA)
            ->deleteJson("/api/v1/auth/sessions/{$secondarySession->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('user_sessions', [
            'id' => $secondarySession->id,
        ]);

        $this->assertNotNull(
            UserSession::query()->findOrFail($secondarySession->id)->revoked_at,
        );

        $this->app['auth']->forgetGuards();

        $this->withToken($tokenB)
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    private function login(
        string $email,
        string $password,
        string $deviceName
    ): string {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName,
        ])->assertOk()->json('token');
    }
}
