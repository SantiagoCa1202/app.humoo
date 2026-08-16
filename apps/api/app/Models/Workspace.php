<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends BaseModel
{
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'workspace_memberships'
        )->withPivot([
            'id',
            'role_id',
            'status',
            'joined_at',
        ]);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->latestOfMany('current_period_end');
    }
}
