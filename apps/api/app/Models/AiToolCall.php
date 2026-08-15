<?php

namespace App\Models;

class AiToolCall extends BaseModel
{
    protected function casts(): array
    {
        return [
            'arguments_json' => 'array',
            'result_ref_json' => 'array',
        ];
    }

    public function run()
    {
        return $this->belongsTo(
            AiRun::class,
            'ai_run_id'
        );
    }
}
