<?php

namespace App\Application\Actions\Beo;

use App\Models\Beo;
use App\Models\BeoImportBatch;
use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventFunction;
use App\Models\EventFunctionDietaryRequirement;
use App\Models\EventFunctionInstruction;
use App\Models\EventOrderReference;
use App\Models\Property;
use App\Models\Venue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBeoImportBatch
{
    public function execute(string $workspaceId, string $userId, array $data): BeoImportBatch
    {
        $this->assertWorkspaceRelation(Property::class, $data['property_id'] ?? null, 'property_id', $workspaceId);
        $this->assertWorkspaceRelation(Document::class, $data['document_id'] ?? null, 'document_id', $workspaceId);

        foreach ($data['event_orders'] as $orderIndex => $orderData) {
            $this->assertWorkspaceRelation(
                Event::class,
                $orderData['event_id'] ?? null,
                "event_orders.{$orderIndex}.event_id",
                $workspaceId
            );
            $this->assertWorkspaceRelation(
                Property::class,
                $orderData['property_id'] ?? ($data['property_id'] ?? null),
                "event_orders.{$orderIndex}.property_id",
                $workspaceId
            );

            foreach ($orderData['versions'] as $versionIndex => $versionData) {
                $this->assertWorkspaceRelation(
                    Document::class,
                    $versionData['document_id'] ?? ($data['document_id'] ?? null),
                    "event_orders.{$orderIndex}.versions.{$versionIndex}.document_id",
                    $workspaceId
                );

                foreach ($versionData['functions'] ?? [] as $functionIndex => $functionData) {
                    foreach ($functionData['venue_ids'] ?? [] as $venueIndex => $venueId) {
                        $this->assertWorkspaceRelation(
                            Venue::class,
                            $venueId,
                            "event_orders.{$orderIndex}.versions.{$versionIndex}.functions.{$functionIndex}.venue_ids.{$venueIndex}",
                            $workspaceId
                        );
                    }
                }
            }
        }

        return DB::transaction(function () use ($data, $userId, $workspaceId): BeoImportBatch {
            $batch = BeoImportBatch::query()->create([
                'workspace_id' => $workspaceId,
                'property_id' => $data['property_id'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'original_filename' => trim($data['original_filename']),
                'source_system' => $data['source_system'] ?? null,
                'status' => $data['status'] ?? 'received',
                'source_metadata' => $data['source_metadata'] ?? null,
                'created_by' => $userId,
            ]);

            $orderGroups = collect($data['event_orders'])->groupBy(
                fn (array $orderData): string => implode('|', [
                    trim($orderData['event_order_number']),
                    $orderData['source_system'] ?? $batch->source_system ?? '',
                ])
            );

            foreach ($orderGroups as $orderGroup) {
                $orderData = $orderGroup->first();
                $sourceSystem = $orderData['source_system'] ?? $batch->source_system;
                $order = Beo::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('event_order_number', trim($orderData['event_order_number']))
                    ->where('source_system', $sourceSystem)
                    ->first();

                if (!$order) {
                    $order = Beo::query()->create([
                    'workspace_id' => $workspaceId,
                    'import_batch_id' => $batch->id,
                    'property_id' => $orderData['property_id'] ?? $batch->property_id,
                    'event_id' => $orderData['event_id'] ?? null,
                    'event_order_number' => trim($orderData['event_order_number']),
                    'quote_number' => $orderData['quote_number'] ?? null,
                    'folio_number' => $orderData['folio_number'] ?? null,
                    'source_organization' => $orderData['source_organization'] ?? null,
                    'source_system' => $sourceSystem,
                    'current_version' => 0,
                    'status' => 'active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    ]);
                } else {
                    $order->forceFill([
                        'import_batch_id' => $batch->id,
                        'property_id' => $order->property_id ?? ($orderData['property_id'] ?? $batch->property_id),
                        'updated_by' => $userId,
                    ])->save();
                }

                $nextVersion = (int) $order->current_version;
                foreach ($orderGroup->flatMap(fn (array $item) => $item['versions'])->values() as $versionData) {
                    $requestedVersion = isset($versionData['version'])
                        ? (int) $versionData['version']
                        : null;
                    $versionNumber = $requestedVersion && $requestedVersion > $nextVersion
                        ? $requestedVersion
                        : $nextVersion + 1;

                    while (BeoVersion::query()
                        ->where('beo_id', $order->id)
                        ->where('version', $versionNumber)
                        ->exists()) {
                        $versionNumber++;
                    }

                    $nextVersion = max($nextVersion, $versionNumber);
                    $version = BeoVersion::query()->create([
                        'workspace_id' => $workspaceId,
                        'beo_id' => $order->id,
                        'document_id' => $versionData['document_id'] ?? $batch->document_id,
                        'version' => $versionNumber,
                        'revision_number' => $versionData['revision_number'] ?? null,
                        'revision_label' => $versionData['revision_label'] ?? null,
                        'revision_type' => $versionData['revision_type'] ?? null,
                        'date_printed' => $versionData['date_printed'] ?? null,
                        'source_pages' => $versionData['source_pages'] ?? null,
                        'source_metadata' => $versionData['source_metadata'] ?? null,
                        'status' => $versionData['status'] ?? 'review_required',
                        'review_status' => $versionData['review_status'] ?? 'pending',
                        'source' => 'import',
                        'created_by' => $userId,
                    ]);

                    $functionsByKey = [];
                    foreach ($versionData['functions'] ?? [] as $functionData) {
                        $function = EventFunction::query()->create([
                            'workspace_id' => $workspaceId,
                            'beo_version_id' => $version->id,
                            ...Arr::only($functionData, [
                                'source_function_key', 'source_function_name', 'function_type',
                                'operational_category', 'post_as', 'start_at', 'end_at',
                                'source_start_time', 'source_end_time', 'source_location_text',
                                'expected_count', 'guaranteed_count', 'set_count', 'production_count',
                                'menu_status', 'operational_signals', 'source_metadata', 'review_metadata',
                            ]),
                        ]);

                        if (!empty($functionData['venue_ids'])) {
                            $function->venues()->attach(
                                collect($functionData['venue_ids'])->mapWithKeys(
                                    fn (string $venueId) => [$venueId => ['workspace_id' => $workspaceId]]
                                )->all()
                            );
                        }

                        foreach ($functionData['dietary_requirements'] ?? [] as $requirement) {
                            EventFunctionDietaryRequirement::query()->create([
                                'workspace_id' => $workspaceId,
                                'event_function_id' => $function->id,
                                ...Arr::only($requirement, [
                                    'guest_name', 'count', 'raw_restriction', 'normalized_restriction',
                                    'category', 'source_text', 'source_reference',
                                ]),
                            ]);
                        }

                        foreach ($functionData['instructions'] ?? [] as $instruction) {
                            EventFunctionInstruction::query()->create([
                                'workspace_id' => $workspaceId,
                                'event_function_id' => $function->id,
                                'category' => $instruction['category'] ?? 'general',
                                'raw_text' => $instruction['raw_text'],
                                'normalized_text' => $instruction['normalized_text'] ?? null,
                                'source_reference' => $instruction['source_reference'] ?? null,
                            ]);
                        }

                        if (!empty($functionData['source_function_key'])) {
                            $functionsByKey[$functionData['source_function_key']] = $function;
                        }
                    }

                    foreach ($versionData['references'] ?? [] as $reference) {
                        EventOrderReference::query()->create([
                            'workspace_id' => $workspaceId,
                            'source_beo_id' => $order->id,
                            'source_beo_version_id' => $version->id,
                            'source_event_function_id' => isset($reference['source_event_function_key'])
                                ? ($functionsByKey[$reference['source_event_function_key']]->id ?? null)
                                : null,
                            'target_event_order_number' => trim($reference['target_event_order_number']),
                            'reference_type' => $reference['reference_type'] ?? null,
                            'raw_text' => $reference['raw_text'],
                            'source_reference' => $reference['source_reference'] ?? null,
                        ]);
                    }

                    if ($versionNumber >= $order->current_version) {
                        $order->forceFill(['current_version' => $versionNumber])->save();
                    }
                }
            }

            return $batch->load([
                'property', 'createdBy',
                'eventOrders.latestVersion.functions.venues',
                'eventOrders.latestVersion.functions.dietaryRequirements',
                'eventOrders.latestVersion.functions.instructions',
            ]);
        });
    }

    private function assertWorkspaceRelation(
        string $modelClass,
        ?string $id,
        string $field,
        string $workspaceId
    ): void {
        if (!$id || $modelClass::query()->whereKey($id)->where('workspace_id', $workspaceId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The related record must belong to the active workspace.',
        ]);
    }
}
