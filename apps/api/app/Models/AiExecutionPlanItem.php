<?php

namespace App\Models;

class AiExecutionPlanItem extends BaseModel
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'depends_on_json' => 'array',
            'input_bindings_json' => 'array',
            'input_json' => 'array',
            'preview_json' => 'array',
            'result_ref_json' => 'array',
            'started_at' => 'datetime',
        ];
    }

    public function confirmation()
    {
        return $this->belongsTo(ActionConfirmation::class, 'action_confirmation_id');
    }

    public function plan()
    {
        return $this->belongsTo(AiExecutionPlan::class, 'execution_plan_id');
    }
}
