<?php

namespace App\Models;

class PrepListVersion extends WorkspaceModel
{
    public function prepList()
    {
        return $this->belongsTo(PrepList::class);
    }

    public function sections()
    {
        return $this->hasMany(PrepSection::class);
    }
}
