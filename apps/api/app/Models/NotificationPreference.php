<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends BaseModel
{
    public const SUPPORTED_EVENT_KEYS = [
        'task.assigned',
        'prep.assigned',
    ];

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'push' => 'boolean',
            'email' => 'boolean',
            'quiet_hours_enabled' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
