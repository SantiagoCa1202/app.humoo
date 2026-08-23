<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'default_guest_count' => 'integer',
            'metadata' => 'array',
            'current_version' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MenuVersion::class)
            ->orderByDesc('version');
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(MenuVersion::class)
            // current_version is stored on menus, so keep the parent row in
            // the relation query when Eloquent eager-loads multiple menus.
            ->join('menus as current_menus', function ($join): void {
                $join
                    ->on('current_menus.id', '=', 'menu_versions.menu_id')
                    ->on('current_menus.workspace_id', '=', 'menu_versions.workspace_id');
            })
            ->whereColumn('menu_versions.version', 'current_menus.current_version')
            ->select('menu_versions.*');
    }

    public function eventAssignments(): HasMany
    {
        return $this->hasMany(EventMenu::class)
            ->latest('assigned_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
