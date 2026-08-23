<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunctionalAcceptanceBeoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_beo_revision_is_reviewable_without_mutating_canonical_event(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $registration = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'BEO',
            'last_name' => 'Reviewer',
            'email' => 'beo-reviewer@humoo.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'acceptance-suite',
        ])->assertCreated();
        $token = (string) $registration->json('token');
        $workspace = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Humoo BEO Acceptance Kitchen',
            'timezone' => 'America/New_York',
            'currency' => 'usd',
        ])->assertCreated();
        $workspaceId = (string) $workspace->json('data.workspace.id');
        $headers = fn () => $this->withToken($token)->withHeader('X-Workspace-ID', $workspaceId);

        $venue = $headers()->postJson('/api/v1/venues', [
            'name' => 'Carolina Ballroom',
            'city' => 'Charlotte',
            'state' => 'NC',
            'timezone' => 'America/New_York',
        ])->assertCreated();

        $event = $headers()->postJson('/api/v1/events', [
            'name' => 'Canonical Acceptance Event',
            'starts_at' => now()->addDays(9)->toIso8601String(),
            'timezone' => 'America/New_York',
            'guest_count_expected' => 150,
            'status' => 'confirmed',
            'priority' => 'normal',
        ])->assertCreated();
        $eventId = (string) $event->json('data.id');

        $response = $headers()
            ->postJson('/api/v1/beo-import-batches', [
                'original_filename' => 'acceptance-revision.pdf',
                'source_system' => 'acceptance-fixture',
                'event_orders' => [[
                    'event_order_number' => 'EO-ACCEPTANCE-001',
                    'event_id' => $eventId,
                    'versions' => [[
                        'revision_number' => 2,
                        'revision_label' => 'Revision 2',
                        'revision_type' => 'change',
                        'status' => 'review_required',
                        'functions' => [[
                            'source_function_name' => 'Leadership Dinner',
                            'source_start_time' => '6:00 PM',
                            'source_location_text' => 'Carolina Ballroom',
                            'guaranteed_count' => 175,
                            'menu_status' => 'available',
                            'venue_ids' => [$venue->json('data.id')],
                        ]],
                    ]],
                ]],
            ])->assertCreated();

        $response->assertJsonPath('data.event_orders.0.latest_version.revision_number', 2);

        $this->assertDatabaseHas('beo_versions', [
            'revision_number' => 2,
            'review_status' => 'pending',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'name' => 'Canonical Acceptance Event',
            'guest_count_expected' => 150,
            'version' => 1,
        ]);

        $headers()->getJson('/api/v1/event-orders')
            ->assertOk()
            ->assertJsonFragment(['event_order_number' => 'EO-ACCEPTANCE-001']);
    }
}
