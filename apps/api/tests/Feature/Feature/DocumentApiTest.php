<?php

namespace Tests\Feature\Feature;

use App\Models\Document;
use App\Models\Event;
use App\Models\ExtractedField;
use App\Models\ExtractionRun;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_user_can_upload_beo_document_and_link_it_to_an_event(): void
    {
        Storage::fake(config('filesystems.default', 'local'));
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Documents Launch Dinner',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(4),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'version' => 1,
            'created_by' => User::query()->where('email', 'owner@humoo.local')->firstOrFail()->id,
            'updated_by' => User::query()->where('email', 'owner@humoo.local')->firstOrFail()->id,
        ]);

        $token = $this->login('owner@humoo.local', 'password');

        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/documents', [
                'type' => 'beo',
                'source' => 'upload',
                'event_id' => $event->id,
                'file' => UploadedFile::fake()->create(
                    'launch-beo.pdf',
                    32,
                    'application/pdf'
                ),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'beo')
            ->assertJsonPath('data.linked_event.id', $event->id)
            ->assertJsonPath('data.latest_beo_version.version', 1);

        $documentId = (string) $response->json('data.id');

        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'workspace_id' => $workspace->id,
            'type' => 'beo',
            'processing_status' => 'uploaded',
        ]);

        $this->assertDatabaseHas('document_links', [
            'document_id' => $documentId,
            'entity_type' => 'event',
            'entity_id' => $event->id,
            'relationship_type' => 'beo',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('beos', [
            'workspace_id' => $workspace->id,
            'event_id' => $event->id,
            'current_version' => 1,
        ]);

        $this->assertDatabaseHas('beo_versions', [
            'document_id' => $documentId,
            'version' => 1,
        ]);
    }

    public function test_document_review_updates_corrected_fields_and_marks_version_ready(): void
    {
        Storage::fake(config('filesystems.default', 'local'));
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Review Dinner',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'version' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $token = $this->login('owner@humoo.local', 'password');

        $uploadResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/documents', [
                'type' => 'beo',
                'source' => 'upload',
                'event_id' => $event->id,
                'file' => UploadedFile::fake()->create(
                    'review-beo.pdf',
                    32,
                    'application/pdf'
                ),
            ])->assertCreated();

        $documentId = (string) $uploadResponse->json('data.id');
        $document = Document::query()->findOrFail($documentId);
        $run = ExtractionRun::query()
            ->where('document_id', $document->id)
            ->latest('created_at')
            ->firstOrFail();

        ExtractedField::query()->create([
            'workspace_id' => $workspace->id,
            'extraction_run_id' => $run->id,
            'field_key' => 'event.guest_count_expected',
            'value_type' => 'integer',
            'value_text' => '120',
            'raw_value' => '120 guests',
            'reviewed' => false,
            'review_status' => 'pending',
        ]);

        $run->touch();
        $expectedUpdatedAt = $run->fresh()->updated_at?->toISOString();

        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/documents/{$document->id}/review", [
                'expected_updated_at' => $expectedUpdatedAt,
                'fields' => [[
                    'id' => $run->fields()->firstOrFail()->id,
                    'review_status' => 'corrected',
                    'corrected_value_text' => '144',
                ]],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.run.status', 'completed')
            ->assertJsonPath('data.version.status', 'approved')
            ->assertJsonPath('data.fields.0.corrected_value_text', '144');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'processing_status' => 'ready',
        ]);

        $this->assertDatabaseHas('extracted_fields', [
            'extraction_run_id' => $run->id,
            'review_status' => 'corrected',
            'corrected_value_text' => '144',
        ]);

        $this->assertDatabaseHas('beo_version_changes', [
            'to_version_id' => $document->latestBeoVersion()->firstOrFail()->id,
            'field_key' => 'event.guest_count_expected',
        ]);
    }

    public function test_stale_document_review_returns_conflict(): void
    {
        Storage::fake(config('filesystems.default', 'local'));
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Conflict Dinner',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'version' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $token = $this->login('owner@humoo.local', 'password');

        $uploadResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/documents', [
                'type' => 'beo',
                'source' => 'upload',
                'event_id' => $event->id,
                'file' => UploadedFile::fake()->create(
                    'conflict-beo.pdf',
                    32,
                    'application/pdf'
                ),
            ])->assertCreated();

        $document = Document::query()->findOrFail((string) $uploadResponse->json('data.id'));
        $run = ExtractionRun::query()
            ->where('document_id', $document->id)
            ->latest('created_at')
            ->firstOrFail();
        $field = ExtractedField::query()->create([
            'workspace_id' => $workspace->id,
            'extraction_run_id' => $run->id,
            'field_key' => 'event.name',
            'value_type' => 'string',
            'value_text' => 'Conflict Dinner',
            'reviewed' => false,
            'review_status' => 'pending',
        ]);

        $staleUpdatedAt = $run->updated_at?->toISOString();
        $run->forceFill(['updated_at' => now()->addSecond()])->save();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/documents/{$document->id}/review", [
                'expected_updated_at' => $staleUpdatedAt,
                'fields' => [[
                    'id' => $field->id,
                    'review_status' => 'accepted',
                ]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-web',
        ])->assertOk()->json('token');
    }
}
