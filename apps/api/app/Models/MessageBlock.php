<?php

namespace App\Models;

class MessageBlock extends BaseModel
{
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'payload_json' => 'array',
            'refreshable' => 'boolean',
            'stale_at' => 'datetime',
        ];
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
