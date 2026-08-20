<?php

namespace App\Support;

use App\Models\BeoVersion;
use App\Models\BeoVersionChange;
use App\Models\ExtractedField;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BeoVersionComparer
{
    public function buildSnapshotFromFields(Collection $fields): array
    {
        $snapshot = [];

        foreach ($fields as $field) {
            if (!$field instanceof ExtractedField) {
                continue;
            }

            $value = $this->resolveFieldValue($field);
            $segments = explode('.', $field->field_key);
            $cursor = &$snapshot;

            foreach ($segments as $index => $segment) {
                if ($segment === '') {
                    continue;
                }

                if ($index === count($segments) - 1) {
                    $cursor[$segment] = $value;
                    continue;
                }

                if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }

                $cursor = &$cursor[$segment];
            }

            unset($cursor);
        }

        return $snapshot;
    }

    public function syncChanges(BeoVersion $version): Collection
    {
        $version->loadMissing('beo');

        $previousVersion = BeoVersion::query()
            ->where('beo_id', $version->beo_id)
            ->where('version', '<', $version->version)
            ->orderByDesc('version')
            ->first();

        $before = $this->flattenSnapshot($previousVersion?->snapshot_json ?? []);
        $after = $this->flattenSnapshot($version->snapshot_json ?? []);

        $changes = collect();

        DB::transaction(function () use ($after, $before, $changes, $previousVersion, $version): void {
            BeoVersionChange::query()
                ->where('to_version_id', $version->id)
                ->delete();

            foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $fieldKey) {
                $previousValueExists = array_key_exists($fieldKey, $before);
                $nextValueExists = array_key_exists($fieldKey, $after);

                if (
                    $previousValueExists === $nextValueExists
                    && $previousValueExists
                    && $before[$fieldKey] === $after[$fieldKey]
                ) {
                    continue;
                }

                $change = BeoVersionChange::query()->create([
                    'workspace_id' => $version->workspace_id,
                    'beo_id' => $version->beo_id,
                    'from_version_id' => $previousVersion?->id,
                    'to_version_id' => $version->id,
                    'field_key' => $fieldKey,
                    'before_value' => $previousValueExists ? ['value' => $before[$fieldKey]] : null,
                    'after_value' => $nextValueExists ? ['value' => $after[$fieldKey]] : null,
                    'severity' => $this->resolveSeverity($fieldKey),
                    'affects_production' => $this->affectsProduction($fieldKey),
                    'reviewed' => false,
                ]);

                $changes->push($change);
            }
        });

        return $changes;
    }

    public function buildImpactSummary(BeoVersion $version): array
    {
        $changes = $version->changes()
            ->get();

        $eventChanges = $changes->filter(
            fn (BeoVersionChange $change) => $change->affects_production
        );

        if ($eventChanges->isEmpty()) {
            return [];
        }

        $event = $version->beo?->event;

        return [[
            'id' => sprintf('event-impact-%s', $version->id),
            'entity_id' => $event?->id,
            'entity_type' => 'event',
            'impact_type' => 'beo_version_change',
            'requires_regeneration' => false,
            'requires_review' => true,
            'severity' => $eventChanges->contains(fn (BeoVersionChange $change) => $change->severity === 'critical')
                ? 'danger'
                : 'warning',
            'summary' => sprintf(
                '%d operational field(s) changed in this BEO version.',
                $eventChanges->count()
            ),
            'title' => $event?->name ?: 'Event impact',
            'translation_key' => 'documents.impact.entities.event',
        ]];
    }

    private function flattenSnapshot(array $snapshot, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($snapshot as $key => $value) {
            $fieldKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if ($this->isAssociativeArray($value)) {
                $flattened += $this->flattenSnapshot($value, $fieldKey);
                continue;
            }

            $flattened[$fieldKey] = $value;
        }

        return $flattened;
    }

    private function isAssociativeArray(mixed $value): bool
    {
        return is_array($value) && array_keys($value) !== range(0, count($value) - 1);
    }

    private function resolveFieldValue(ExtractedField $field): mixed
    {
        if ($field->corrected_value_json !== null) {
            return $field->corrected_value_json;
        }

        if ($field->corrected_value_text !== null && $field->corrected_value_text !== '') {
            return $field->corrected_value_text;
        }

        if ($field->value_json !== null) {
            return $field->value_json;
        }

        return $field->value_text;
    }

    private function affectsProduction(string $fieldKey): bool
    {
        return str_starts_with($fieldKey, 'event.')
            || in_array($fieldKey, [
                'guest_count',
                'guest_count_confirmed',
                'guest_count_expected',
                'service_type',
                'timezone',
                'venue',
                'client',
            ], true);
    }

    private function resolveSeverity(string $fieldKey): string
    {
        if (in_array($fieldKey, ['event.starts_at', 'event.ends_at', 'guest_count', 'guest_count_expected'], true)) {
            return 'critical';
        }

        return $this->affectsProduction($fieldKey)
            ? 'warning'
            : 'info';
    }
}
