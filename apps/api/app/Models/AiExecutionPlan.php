<?php

namespace App\Models;

class AiExecutionPlan extends BaseModel
{
    public const MAX_ITEMS = 50;

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'summary_json' => 'array',
        ];
    }

    public function confirmation()
    {
        return $this->belongsTo(ActionConfirmation::class, 'confirmation_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(AiExecutionPlanItem::class, 'execution_plan_id');
    }

    public function progressMessage()
    {
        return $this->belongsTo(Message::class, 'progress_message_id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
