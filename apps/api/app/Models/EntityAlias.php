<?php

namespace App\Models;

class EntityAlias extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'confirmation_count' => 'integer',
        ];
    }
}
