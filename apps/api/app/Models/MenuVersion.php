<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuVersion extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'locked' => 'boolean',
            'locked_at' => 'datetime',
            'metadata' => 'array',
            'revision' => 'integer',
            'version' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MenuSection::class)
            ->orderBy('position');
    }

    public function eventAssignments(): HasMany
    {
        return $this->hasMany(EventMenu::class)
            ->latest('assigned_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
