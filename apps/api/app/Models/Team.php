<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function leadMembership(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceMembership::class,
            'lead_membership_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkspaceMembership::class,
            'team_members',
            'team_id',
            'membership_id'
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

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}
