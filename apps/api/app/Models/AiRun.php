<?php

namespace App\Models;

class AiRun extends BaseModel
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'deadline_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'metadata' => 'array',
            'progress_meta' => 'array',
            'queued_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'started_at' => 'datetime',
            'usage_json' => 'array',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function assistantMessage()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function executionPlan()
    {
        return $this->belongsTo(AiExecutionPlan::class, 'execution_plan_id');
    }

    public function inputMessage()
    {
        return $this->belongsTo(Message::class, 'input_message_id');
    }

    public function objective()
    {
        return $this->belongsTo(AiObjective::class, 'objective_id');
    }

    public function toolCalls()
    {
        return $this->hasMany(AiToolCall::class);
    }
}
