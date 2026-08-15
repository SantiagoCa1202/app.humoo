<?php

namespace App\Models;

class Conversation extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function participants()
    {
        return $this->hasMany(
            ConversationParticipant::class
        );
    }

    public function summaries()
    {
        return $this->hasMany(
            ConversationSummary::class
        );
    }
}
