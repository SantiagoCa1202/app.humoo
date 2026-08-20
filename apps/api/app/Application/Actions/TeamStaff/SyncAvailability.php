<?php

namespace App\Application\Actions\TeamStaff;

use App\Models\Availability;
use App\Models\AvailabilityRule;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncAvailability
{
    public function execute(
        WorkspaceMembership $membership,
        array $payload
    ): array {
        return DB::transaction(function () use ($membership, $payload): array {
            $this->assertWorkspaceScope($membership, $payload);

            Availability::query()
                ->where('workspace_id', $membership->workspace_id)
                ->where('membership_id', $membership->id)
                ->delete();

            AvailabilityRule::query()
                ->where('workspace_id', $membership->workspace_id)
                ->where('membership_id', $membership->id)
                ->delete();

            $records = collect($payload['records'] ?? [])->map(function ($record) use ($membership) {
                $this->assertDateOrder(
                    $record['starts_at'] ?? null,
                    $record['ends_at'] ?? null,
                    'records'
                );

                return Availability::query()->create([
                    'workspace_id' => $membership->workspace_id,
                    'membership_id' => $membership->id,
                    'starts_at' => $record['starts_at'],
                    'ends_at' => $record['ends_at'],
                    'timezone' => $record['timezone'],
                    'available' => $record['available'] ?? true,
                    'type' => $record['type'] ?? (($record['available'] ?? true) ? 'available' : 'unavailable'),
                    'source' => $record['source'] ?? 'user',
                    'notes' => $this->trimOrNull($record['notes'] ?? null),
                ]);
            })->all();

            $rules = collect($payload['rules'] ?? [])->map(function ($rule) use ($membership) {
                if (
                    isset($rule['starts_at'], $rule['ends_at'])
                    && strcmp((string) $rule['ends_at'], (string) $rule['starts_at']) <= 0
                ) {
                    throw ValidationException::withMessages([
                        'rules' => ['Each availability rule must end after it starts.'],
                    ]);
                }

                return AvailabilityRule::query()->create([
                    'workspace_id' => $membership->workspace_id,
                    'membership_id' => $membership->id,
                    'day_of_week' => $rule['day_of_week'],
                    'starts_at' => $rule['starts_at'],
                    'ends_at' => $rule['ends_at'],
                    'timezone' => $rule['timezone'],
                    'available' => $rule['available'] ?? true,
                    'effective_from' => $rule['effective_from'] ?? null,
                    'effective_until' => $rule['effective_until'] ?? null,
                    'active' => $rule['active'] ?? true,
                ]);
            })->all();

            return [
                'records' => $records,
                'rules' => $rules,
            ];
        });
    }

    private function assertWorkspaceScope(
        WorkspaceMembership $membership,
        array $payload
    ): void {
        if (
            isset($payload['membership_id'])
            && $payload['membership_id'] !== $membership->id
        ) {
            throw ValidationException::withMessages([
                'membership_id' => ['Availability payload does not match the target membership.'],
            ]);
        }
    }

    private function assertDateOrder(
        ?string $startsAt,
        ?string $endsAt,
        string $field
    ): void {
        if (!$startsAt || !$endsAt) {
            return;
        }

        if (strtotime($endsAt) <= strtotime($startsAt)) {
            throw ValidationException::withMessages([
                $field => ['Availability end time must be after the start time.'],
            ]);
        }
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
