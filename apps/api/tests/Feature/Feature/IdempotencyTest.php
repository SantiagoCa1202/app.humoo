<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_key_cannot_be_reused_in_the_same_workspace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $payload = [
            'id' => (string) Str::ulid(),
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'key' => 'evt-create-001',
            'action' => 'events.create',
            'request_hash' => hash('sha512', '{"name":"Boda"}'),
            'status' => 'completed',
            'response_status' => 201,
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('idempotency_keys')->insert($payload);

        $this->expectException(QueryException::class);

        DB::table('idempotency_keys')->insert([
            ...$payload,
            'id' => (string) Str::ulid(),
            'request_hash' => hash('sha512', '{"name":"Boda 2"}'),
        ]);
    }

    public function test_same_key_can_be_reused_in_another_workspace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $primaryWorkspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $secondaryWorkspace = Workspace::query()->create([
            'name' => 'Second Workspace',
            'slug' => 'second-workspace',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        foreach ([$primaryWorkspace, $secondaryWorkspace] as $workspace) {
            DB::table('idempotency_keys')->insert([
                'id' => (string) Str::ulid(),
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'key' => 'evt-create-001',
                'action' => 'events.create',
                'request_hash' => hash('sha512', $workspace->id),
                'status' => 'completed',
                'response_status' => 201,
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertDatabaseCount('idempotency_keys', 2);
    }
}
