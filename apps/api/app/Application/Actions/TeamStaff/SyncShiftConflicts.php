<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Availability;
use App\Models\Shift;
use App\Models\ShiftConflict;

class SyncShiftConflicts
{
    public function execute(Shift $shift): array
    {
        ShiftConflict::query()
            ->where('workspace_id', $shift->workspace_id)
            ->where('shift_id', $shift->id)
            ->delete();

        $conflicts = [];

        foreach ($this->findOverlappingShifts($shift) as $relatedShift) {
            $conflicts[] = ShiftConflict::query()->create([
                'workspace_id' => $shift->workspace_id,
                'membership_id' => $shift->membership_id,
                'shift_id' => $shift->id,
                'type' => 'overlap',
                'severity' => 'critical',
                'message' => 'This member already has an overlapping shift.',
                'details' => [
                    'related_shift' => $this->serializeShiftReference($relatedShift),
                ],
            ]);
        }

        if ($this->isUnavailable($shift)) {
            $conflicts[] = ShiftConflict::query()->create([
                'workspace_id' => $shift->workspace_id,
                'membership_id' => $shift->membership_id,
                'shift_id' => $shift->id,
                'type' => 'unavailable',
                'severity' => 'warning',
                'message' => 'This member is marked unavailable during the shift window.',
                'details' => [],
            ]);
        }

        $stationCapacity = $this->detectStationCapacityConflict($shift);
        if ($stationCapacity !== null) {
            $conflicts[] = ShiftConflict::query()->create([
                'workspace_id' => $shift->workspace_id,
                'membership_id' => $shift->membership_id,
                'shift_id' => $shift->id,
                'type' => 'station_capacity',
                'severity' => 'warning',
                'message' => 'Station capacity is exceeded for this time window.',
                'details' => $stationCapacity,
            ]);
        }

        return $conflicts;
    }

    private function findOverlappingShifts(Shift $shift)
    {
        return Shift::query()
            ->where('workspace_id', $shift->workspace_id)
            ->where('membership_id', $shift->membership_id)
            ->whereKeyNot($shift->id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $shift->ends_at)
            ->where('ends_at', '>', $shift->starts_at)
            ->with(['event', 'membership.user', 'station', 'team'])
            ->get();
    }

    private function isUnavailable(Shift $shift): bool
    {
        return Availability::query()
            ->where('workspace_id', $shift->workspace_id)
            ->where('membership_id', $shift->membership_id)
            ->where('available', false)
            ->where('starts_at', '<', $shift->ends_at)
            ->where('ends_at', '>', $shift->starts_at)
            ->exists();
    }

    private function detectStationCapacityConflict(Shift $shift): ?array
    {
        if (!$shift->station_id || !$shift->relationLoaded('station') || !$shift->station?->capacity) {
            return null;
        }

        $assigned = Shift::query()
            ->where('workspace_id', $shift->workspace_id)
            ->where('station_id', $shift->station_id)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $shift->ends_at)
            ->where('ends_at', '>', $shift->starts_at)
            ->count();

        if ($assigned <= $shift->station->capacity) {
            return null;
        }

        return [
            'assigned_staff' => $assigned,
            'required_staff' => $shift->station->capacity,
            'station' => [
                'id' => $shift->station->id,
                'name' => $shift->station->name,
                'status' => $shift->station->status,
            ],
        ];
    }

    private function serializeShiftReference(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'membership_id' => $shift->membership_id,
            'event_id' => $shift->event_id,
            'team_id' => $shift->team_id,
            'station_id' => $shift->station_id,
            'starts_at' => $shift->starts_at?->toIso8601String(),
            'ends_at' => $shift->ends_at?->toIso8601String(),
            'timezone' => $shift->timezone,
            'role' => $shift->role,
            'status' => $shift->status,
            'notes' => $shift->notes,
            'event' => $shift->event ? [
                'id' => $shift->event->id,
                'name' => $shift->event->name,
                'starts_at' => $shift->event->starts_at?->toIso8601String(),
                'timezone' => $shift->event->timezone,
            ] : null,
            'team' => $shift->team ? [
                'id' => $shift->team->id,
                'key' => $shift->team->key,
                'name' => $shift->team->name,
                'status' => $shift->team->status,
            ] : null,
            'station' => $shift->station ? [
                'id' => $shift->station->id,
                'name' => $shift->station->name,
                'status' => $shift->station->status,
            ] : null,
            'member' => $shift->membership && $shift->membership->relationLoaded('user') && $shift->membership->user ? [
                'id' => $shift->membership->id,
                'user_id' => $shift->membership->user_id,
                'name' => $shift->membership->user->name,
                'email' => $shift->membership->user->email,
                'status' => $shift->membership->status,
            ] : null,
        ];
    }
}
