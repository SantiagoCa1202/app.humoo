<?php

namespace App\Models;

class AiObjectiveOperation extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'atomicity_policy_json' => 'array',
            'bindings_json' => 'array',
            'completed_at' => 'datetime',
            'depends_on_json' => 'array',
            'expected_result_keys_json' => 'array',
            'input_json' => 'array',
            'is_required' => 'boolean',
            'result_ref_json' => 'array',
            'retry_policy_json' => 'array',
        ];
    }

    public function objective()
    {
        return $this->belongsTo(AiObjective::class, 'objective_id');
    }

    public function planItems()
    {
        return $this->hasMany(AiExecutionPlanItem::class, 'objective_operation_id');
    }
}
