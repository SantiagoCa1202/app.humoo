<?php

namespace App\Models;

class ActionConfirmation extends BaseModel
{
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'draft_json' => 'array',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
            'result_ref_json' => 'array',
        ];
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function toolCall()
    {
        return $this->belongsTo(
            AiToolCall::class,
            'ai_tool_call_id'
        );
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
