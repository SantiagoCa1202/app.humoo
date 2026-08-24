<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workspace extends BaseModel
{
    protected function casts(): array
    {
        return ['operational_visibility_defaults' => 'array'];
    }
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

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
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

    public function capabilityRequests(): HasMany
    {
        return $this->hasMany(CapabilityRequest::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function beos(): HasMany
    {
        return $this->hasMany(Beo::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function beoImportBatches(): HasMany
    {
        return $this->hasMany(BeoImportBatch::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->latestOfMany('current_period_end');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
