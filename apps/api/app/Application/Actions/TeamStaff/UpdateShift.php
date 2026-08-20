<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Shift;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateShift
{
    private SyncShiftConflicts $syncShiftConflicts;

    public function __construct(SyncShiftConflicts $syncShiftConflicts)
    {
        $this->syncShiftConflicts = $syncShiftConflicts;
    }

    public function execute(Shift $shift, string $userId, array $payload): Shift
    {
        return DB::transaction(function () use ($shift, $userId, $payload): Shift {
            $this->assertStationTeamConsistency($payload, $shift);

            $shift->forceFill([
                'membership_id' => $payload['membership_id'] ?? $shift->membership_id,
                'event_id' => array_key_exists('event_id', $payload) ? $payload['event_id'] : $shift->event_id,
                'team_id' => array_key_exists('team_id', $payload) ? $payload['team_id'] : $shift->team_id,
                'station_id' => array_key_exists('station_id', $payload) ? $payload['station_id'] : $shift->station_id,
                'starts_at' => $payload['starts_at'] ?? $shift->starts_at,
                'ends_at' => $payload['ends_at'] ?? $shift->ends_at,
                'timezone' => trim((string) ($payload['timezone'] ?? $shift->timezone)),
                'break_minutes' => $payload['break_minutes'] ?? $shift->break_minutes,
                'role' => $this->trimOrNull($payload['role'] ?? $shift->role),
                'status' => $payload['status'] ?? $shift->status,
                'notes' => $this->trimOrNull($payload['notes'] ?? $shift->notes),
                'updated_by' => $userId,
            ])->save();

            $shift->load(['event', 'membership.user', 'membership.role', 'station', 'team']);
            $this->syncShiftConflicts->execute($shift);

            return $shift->fresh([
                'conflicts.membership.user',
                'event',
                'membership.role',
                'membership.teams',
                'membership.user',
                'station.team',
                'team',
            ]);
        });
    }

    private function assertStationTeamConsistency(array $payload, Shift $shift): void
    {
        $stationId = $payload['station_id'] ?? $shift->station_id;
        $teamId = $payload['team_id'] ?? $shift->team_id;

        if (!$stationId || !$teamId) {
            return;
        }

        $station = Station::query()->find($stationId);

        if ($station && $station->team_id && $station->team_id !== $teamId) {
            throw ValidationException::withMessages([
                'team_id' => ['The selected team does not match the station team.'],
            ]);
        }
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
