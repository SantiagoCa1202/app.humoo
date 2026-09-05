<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrepList extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'blocked_items' => 'integer',
            'completed_at' => 'datetime',
            'completed_items' => 'integer',
            'current_version' => 'integer',
            'metadata' => 'array',
            'production_ends_at' => 'datetime',
            'production_starts_at' => 'datetime',
            'total_items' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PrepListVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(PrepListVersion::class)
            // current_version is stored on prep_lists, so keep the parent row
            // available while Eloquent eager-loads the current child version.
            ->join('prep_lists as current_prep_lists', function ($join): void {
                $join
                    ->on('current_prep_lists.id', '=', 'prep_list_versions.prep_list_id')
                    ->on('current_prep_lists.workspace_id', '=', 'prep_list_versions.workspace_id');
            })
            ->whereColumn('prep_list_versions.version', 'current_prep_lists.current_version')
            ->select('prep_list_versions.*');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
