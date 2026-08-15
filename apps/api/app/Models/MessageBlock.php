<?php

namespace App\Models;

class MessageBlock extends BaseModel
{
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
        ];
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
