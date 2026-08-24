<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEntityRef extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'last_referenced_at' => 'datetime',
            'metadata_json' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
