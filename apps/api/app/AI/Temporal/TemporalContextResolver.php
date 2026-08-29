<?php

namespace App\AI\Temporal;

use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Builds the server-authoritative temporal snapshot for one AI turn.
 *
 * This class deliberately does not inspect or interpret natural-language
 * messages. The model owns temporal semantics; Laravel only supplies trusted
 * clock/timezone facts and validates model-produced temporal values at the
 * tool boundary.
 */
final class TemporalContextResolver
{
    /**
     * @param array<string, mixed> $activeEntities
     * @return array<string, string|null>
     */
    public function resolve(
        Workspace $workspace,
        User $user,
        string $locale = 'en',
        array $activeEntities = []
    ): array {
        [$timezone, $source] = $this->selectTimezone($workspace, $user, $activeEntities);
        $utcNow = CarbonImmutable::now('UTC');
        $localNow = $utcNow->setTimezone($timezone);

        return [
            'current_utc_datetime' => $utcNow->format('Y-m-d\\TH:i:s\\Z'),
            'current_local_datetime' => $localNow->toIso8601String(),
            'current_local_date' => $localNow->toDateString(),
            'current_local_time' => $localNow->format('H:i'),
            'timezone' => $timezone,
            'timezone_source' => $source,
            'locale' => $locale,
        ];
    }

    /** @param array<string, mixed> $activeEntities @return array{0: string, 1: string} */
    private function selectTimezone(Workspace $workspace, User $user, array $activeEntities): array
    {
        $eventTimezone = $this->activeEventTimezone($activeEntities);
        if ($eventTimezone !== null) {
            return [$eventTimezone, 'entity'];
        }

        $workspaceTimezone = $this->validTimezone($workspace->timezone ?? null);
        if ($workspaceTimezone !== null) {
            return [$workspaceTimezone, 'workspace'];
        }

        $userTimezone = $this->validTimezone($user->timezone ?? null);
        if ($userTimezone !== null) {
            return [$userTimezone, 'user'];
        }

        $fallback = $this->validTimezone(
            config('ai.temporal.fallback_timezone', config('app.timezone', 'UTC'))
        ) ?? 'UTC';

        return [$fallback, 'fallback'];
    }

    /** @param array<string, mixed> $activeEntities */
    private function activeEventTimezone(array $activeEntities): ?string
    {
        foreach ($activeEntities as $reference) {
            if (!is_array($reference) || ($reference['type'] ?? null) !== 'event') {
                continue;
            }

            $timezone = data_get($reference, 'snapshot.timezone') ?? ($reference['timezone'] ?? null);
            $resolved = $this->validTimezone($timezone);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function validTimezone(mixed $timezone): ?string
    {
        if (!is_string($timezone) || trim($timezone) === '') {
            return null;
        }

        try {
            return (new DateTimeZone(trim($timezone)))->getName();
        } catch (\Throwable) {
            return null;
        }
    }
}
