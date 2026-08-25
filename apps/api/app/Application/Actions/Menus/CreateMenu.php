<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

class CreateMenu
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

    public function execute(string $workspaceId, string $userId, array $payload): Menu
    {
        return DB::transaction(function () use ($workspaceId, $userId, $payload): Menu {
            $menu = Menu::query()->create([
                'workspace_id' => $workspaceId,
                'name' => trim((string) $payload['name']),
                'description' => $this->trimOrNull($payload['description'] ?? null),
                'type' => $this->trimOrNull($payload['type'] ?? null),
                'current_version' => 0,
                'status' => $payload['status'] ?? 'draft',
                'default_guest_count' => $payload['default_guest_count'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $version = $this->createMenuVersion->execute(
                $menu,
                $workspaceId,
                $userId,
                $payload
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
