<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

class UpdateMenu
{
    private CreateMenuVersion $createMenuVersion;

    private AssignMenuToEvent $assignMenuToEvent;

    public function __construct(
        CreateMenuVersion $createMenuVersion,
        AssignMenuToEvent $assignMenuToEvent
    ) {
        $this->createMenuVersion = $createMenuVersion;
        $this->assignMenuToEvent = $assignMenuToEvent;
    }

    public function execute(
        Menu $menu,
        string $workspaceId,
        string $userId,
        string $currentVersionId,
        int $expectedRevision,
        array $payload
    ): ?Menu {
        $currentVersion = $menu->currentVersionRecord()
            ->with([
                'sections.items.dietaryTags',
                'sections.items.recipeVersion',
            ])
            ->first();

        if (
            !$currentVersion
            || $currentVersion->id !== $currentVersionId
            || (int) $currentVersion->revision !== $expectedRevision
        ) {
            return null;
        }

        return DB::transaction(function () use (
            $menu,
            $workspaceId,
            $userId,
            $payload,
            $currentVersion
        ): Menu {
            $menu->forceFill([
                'name' => trim((string) $payload['name']),
                'description' => $this->trimOrNull($payload['description'] ?? null),
                'type' => $this->trimOrNull($payload['type'] ?? null),
                'status' => $payload['status'] ?? $menu->status,
                'default_guest_count' => $payload['default_guest_count'] ?? $menu->default_guest_count,
                'metadata' => $payload['metadata'] ?? $menu->metadata,
                'updated_by' => $userId,
            ])->save();

            $version = $this->createMenuVersion->execute(
                $menu,
                $workspaceId,
                $userId,
                $payload,
                $currentVersion,
                'manual'
            );

            $menu->forceFill([
                'current_version' => $version->version,
            ])->save();

            if (array_key_exists('event_id', $payload)) {
                $this->assignMenuToEvent->execute(
                    $menu,
                    $version,
                    $workspaceId,
                    $userId,
                    $payload['event_id'],
                    $payload
                );
            }

            return $menu->fresh();
        });
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
