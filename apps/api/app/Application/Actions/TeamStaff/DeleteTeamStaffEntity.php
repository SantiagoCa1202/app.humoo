<?php

namespace App\Application\Actions\TeamStaff;

use Illuminate\Database\Eloquent\Model;

class DeleteTeamStaffEntity
{
    public function execute(Model $entity): void
    {
        $entity->delete();
    }
}
