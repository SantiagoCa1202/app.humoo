<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMenuItemOverride extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'planned_quantity' => 'decimal:4',
        ];
    }

    public function eventMenu(): BelongsTo
    {
        return $this->belongsTo(EventMenu::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
