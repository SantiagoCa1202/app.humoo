<?php

namespace App\Models;

class PrepSection extends WorkspaceModel
{
    public function version()
    {
        return $this->belongsTo(
            PrepListVersion::class,
            'prep_list_version_id'
        );
    }

    public function items()
    {
        return $this->hasMany(PrepItem::class);
    }
}
