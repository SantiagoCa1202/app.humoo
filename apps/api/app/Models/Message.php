<?php

namespace App\Models;

class Message extends BaseModel
{
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function blocks()
    {
        return $this->hasMany(MessageBlock::class)
            ->orderBy('position');
    }

    public function aiRuns()
    {
        return $this->hasMany(AiRun::class);
    }
}
