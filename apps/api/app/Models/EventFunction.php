<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventFunction extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expected_count' => 'integer',
            'guaranteed_count' => 'integer',
            'set_count' => 'integer',
            'production_count' => 'integer',
            'operational_signals' => 'array',
            'source_metadata' => 'array',
            'review_metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function beoVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class);
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'event_function_venues')
            ->withPivot('workspace_id')
            ->withTimestamps();
    }

    public function dietaryRequirements(): HasMany
    {
        return $this->hasMany(EventFunctionDietaryRequirement::class);
    }

    public function instructions(): HasMany
    {
        return $this->hasMany(EventFunctionInstruction::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(EventOrderReference::class, 'source_event_function_id');
    }

    public function hasSignal(string $signal): bool
    {
        return (bool) ($this->operational_signals[$signal] ?? false);
    }
}
