<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(
            EventGroup::class,
            'event_group_id'
        );
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(EventStaff::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(EventStatusHistory::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(EventMenu::class);
    }

    public function prepLists(): HasMany
    {
        return $this->hasMany(PrepList::class);
    }

    public function beo(): HasOne
    {
        return $this->hasOne(Beo::class);
    }
}
