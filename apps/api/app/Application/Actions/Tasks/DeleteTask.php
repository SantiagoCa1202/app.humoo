<?php

namespace App\Application\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class DeleteTask
{
    public function execute(Task $task): void
    {
        DB::transaction(fn (): ?bool => $task->forceDelete());
    }
}
