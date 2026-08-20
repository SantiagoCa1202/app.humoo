<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Shift;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateShift
{
    private SyncShiftConflicts $syncShiftConflicts;

    public function __construct(SyncShiftConflicts $syncShiftConflicts)
    {
        $this->syncShiftConflicts = $syncShiftConflicts;
    }

    public function execute(string $workspaceId, string $userId, array $payload): Shift
    {
        return DB::transaction(function () use ($workspaceId, $userId, $payload): Shift {
            $this->assertStationTeamConsistency($payload);

            $shift = Shift::query()->create([
                'workspace_id' => $workspaceId,
                'membership_id' => $payload['membership_id'],
                'event_id' => $payload['event_id'] ?? null,
                'team_id' => $payload['team_id'] ?? null,
                'station_id' => $payload['station_id'] ?? null,
                'starts_at' => $payload['starts_at'],
                'ends_at' => $payload['ends_at'],
                'timezone' => trim((string) $payload['timezone']),
                'break_minutes' => $payload['break_minutes'] ?? 0,
                'role' => $this->trimOrNull($payload['role'] ?? null),
                'status' => $payload['status'] ?? 'scheduled',
                'notes' => $this->trimOrNull($payload['notes'] ?? null),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

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

    private function assertStationTeamConsistency(array $payload): void
    {
        if (empty($payload['station_id']) || empty($payload['team_id'])) {
            return;
        }

        $station = Station::query()->find($payload['station_id']);

        if ($station && $station->team_id && $station->team_id !== $payload['team_id']) {
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
