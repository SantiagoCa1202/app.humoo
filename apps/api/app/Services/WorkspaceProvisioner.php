<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\User;
use App\Support\WorkspaceAccessCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceProvisioner
{
    public function __construct(
        private WorkspaceAccessCatalog $workspaceAccessCatalog
    ) {
    }

    public function createForUser(
        User $user,
        array $attributes
    ): WorkspaceMembership {
        return DB::transaction(function () use ($attributes, $user): WorkspaceMembership {
            $workspace = Workspace::query()->create([
                'name' => $attributes['name'],
                'slug' => $this->generateUniqueSlug($attributes['name']),
                'default_locale' => $attributes['default_locale'],
                'timezone' => $attributes['timezone'],
                'currency' => Str::upper($attributes['currency']),
                'status' => 'active',
            ]);

            $this->workspaceAccessCatalog->ensureSystemCatalog();

            $ownerRole = Role::query()
                ->whereNull('workspace_id')
                ->where('key', 'owner')
                ->firstOrFail();

            $membership = WorkspaceMembership::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role_id' => $ownerRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            return $membership->fresh([
                'workspace',
                'role.permissions',
                'user',
            ]);
        });
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug !== '' ? $baseSlug : 'workspace';
        $suffix = 1;

        while (
            Workspace::query()
                ->where('slug', $slug)
                ->exists()
        ) {
            $suffix++;
            $slug = "{$baseSlug}-{$suffix}";

            if ($baseSlug === '') {
                $slug = "workspace-{$suffix}";
            }
        }

        return $slug;
    }
}
