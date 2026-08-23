<?php

namespace App\Models;

class ConversationParticipant extends BaseModel
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'last_read_at' => 'datetime',
            'left_at' => 'datetime',
            'muted' => 'boolean',
        ];
    }
}
