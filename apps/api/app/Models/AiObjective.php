<?php

namespace App\Models;

class AiObjective extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'analyzed_at' => 'datetime',
            'blockers_json' => 'array',
            'confirmed_at' => 'datetime',
            'expected_results_json' => 'array',
            'finished_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'metadata_json' => 'array',
            'paused_at' => 'datetime',
            'required_facts_json' => 'array',
            'resolved_facts_json' => 'array',
            'started_at' => 'datetime',
            'verification_rules_json' => 'array',
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

    public function executionPlans()
    {
        return $this->hasMany(AiExecutionPlan::class, 'objective_id');
    }

    public function operations()
    {
        return $this->hasMany(AiObjectiveOperation::class, 'objective_id');
    }

    public function runs()
    {
        return $this->hasMany(AiRun::class, 'objective_id');
    }

    public function sourceMessage()
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }
}
