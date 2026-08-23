<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkspaceMembership extends BaseModel
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'operational_visibility_overrides' => 'array',
        ];
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_members',
            'membership_id',
            'team_id'
        )->withPivot([
            'id',
            'workspace_id',
            'role',
            'is_lead',
            'status',
            'joined_at',
            'left_at',
        ])->withTimestamps();
    }

    public function teamMemberships(): HasMany
    {
        return $this->hasMany(
            TeamMember::class,
            'membership_id'
        );
    }

    public function availability(): HasMany
    {
        return $this->hasMany(
            Availability::class,
            'membership_id'
        );
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(
            AvailabilityRule::class,
            'membership_id'
        );
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(
            Shift::class,
            'membership_id'
        );
    }
}
