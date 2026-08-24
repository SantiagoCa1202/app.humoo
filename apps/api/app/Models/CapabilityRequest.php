<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapabilityRequest extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'first_requested_at' => 'datetime',
            'last_requested_at' => 'datetime',
            'metadata_json' => 'array',
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

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
