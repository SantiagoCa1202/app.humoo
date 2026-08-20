<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends BaseModel
{
    protected $table = 'team_members';

    protected function casts(): array
    {
        return [
            'is_lead' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceMembership::class,
            'membership_id'
        );
    }
}
