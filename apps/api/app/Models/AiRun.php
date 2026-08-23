<?php

namespace App\Models;

class AiRun extends BaseModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'usage_json' => 'array',
        ];
    }

    public function toolCalls()
    {
        return $this->hasMany(AiToolCall::class);
    }
}
