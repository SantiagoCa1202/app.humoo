<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUlids;
    use Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships()
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()
            ->where('status', 'active');
    }

    public function membershipForWorkspace(string $workspaceId): ?WorkspaceMembership
    {
        return $this->memberships()
            ->with('role.permissions')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->first();
    }

    public function hasWorkspacePermission(string $workspaceId, string $permissionKey): bool
    {
        $membership = $this->membershipForWorkspace($workspaceId);

        if (!$membership?->role) {
            return false;
        }

        return $membership->role
            ->permissions()
            ->where('key', $permissionKey)
            ->exists();
    }

    public function workspaces()
    {
        return $this->belongsToMany(
            Workspace::class,
            'workspace_memberships'
        )->withPivot([
            'id',
            'role_id',
            'status',
            'joined_at',
        ]);
    }

    public function authIdentities()
    {
        return $this->hasMany(AuthIdentity::class);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }
}
