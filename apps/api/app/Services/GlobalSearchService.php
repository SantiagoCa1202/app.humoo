<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Event;
use App\Models\Menu;
use App\Models\PrepList;
use App\Models\Recipe;
use App\Models\Station;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    private const MAX_TOTAL_RESULTS = 50;
    private const MAX_RESULTS_PER_ENTITY = 8;

    public function search(
        Workspace $workspace,
        User $user,
        string $query,
        int $limit = 30,
    ): array {
        $normalizedQuery = trim($query);
        $safeLimit = max(1, min($limit, self::MAX_TOTAL_RESULTS));
        $perEntityLimit = min(self::MAX_RESULTS_PER_ENTITY, $safeLimit);
        $results = collect();

        if ($user->hasWorkspacePermission($workspace->id, 'events.view')) {
            $results = $results->merge($this->events($workspace, $normalizedQuery, $perEntityLimit));
            $results = $results->merge($this->documents($workspace, $normalizedQuery, $perEntityLimit));
        }

        if ($user->hasWorkspacePermission($workspace->id, 'recipes.view')) {
            $results = $results->merge($this->recipes($workspace, $normalizedQuery, $perEntityLimit));
        }

        if ($user->hasWorkspacePermission($workspace->id, 'menus.view')) {
            $results = $results->merge($this->menus($workspace, $normalizedQuery, $perEntityLimit));
        }

        if ($user->hasWorkspacePermission($workspace->id, 'prep_lists.view')) {
            $results = $results->merge($this->prepLists($workspace, $normalizedQuery, $perEntityLimit));
        }

        if ($user->hasWorkspacePermission($workspace->id, 'tasks.view')) {
            $results = $results->merge($this->tasks($workspace, $normalizedQuery, $perEntityLimit));
        }

        if ($user->hasWorkspacePermission($workspace->id, 'members.view')) {
            $results = $results->merge($this->teams($workspace, $normalizedQuery, $perEntityLimit));
            $results = $results->merge($this->stations($workspace, $normalizedQuery, $perEntityLimit));
            $results = $results->merge($this->members($workspace, $normalizedQuery, $perEntityLimit));
        }

        return [
            'query' => $normalizedQuery,
            'results' => $results->take($safeLimit)->values()->all(),
        ];
    }

    private function events(Workspace $workspace, string $query, int $limit): Collection
    {
        return Event::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name', 'event_number']))
            ->with('client:id,name')
            ->select(['id', 'workspace_id', 'name', 'event_number', 'status', 'client_id'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Event $event): array => $this->result(
                'event',
                $event->id,
                $event->name,
                $event->client?->name ?? $event->event_number,
                'event.detail',
                ['status' => $event->status],
            ));
    }

    private function documents(Workspace $workspace, string $query, int $limit): Collection
    {
        return Document::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name', 'original_filename']))
            ->select(['id', 'workspace_id', 'name', 'original_filename', 'processing_status', 'type'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Document $document): array => $this->result(
                'document',
                $document->id,
                $document->name ?: $document->original_filename,
                $document->processing_status,
                'document.detail',
                ['type' => $document->type],
            ));
    }

    private function recipes(Workspace $workspace, string $query, int $limit): Collection
    {
        return Recipe::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name']))
            ->select(['id', 'workspace_id', 'name', 'status'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Recipe $recipe): array => $this->result(
                'recipe',
                $recipe->id,
                $recipe->name,
                $recipe->status,
                'recipe.detail',
            ));
    }

    private function menus(Workspace $workspace, string $query, int $limit): Collection
    {
        return Menu::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name']))
            ->select(['id', 'workspace_id', 'name', 'status'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Menu $menu): array => $this->result(
                'menu',
                $menu->id,
                $menu->name,
                $menu->status,
                'menu.detail',
            ));
    }

    private function prepLists(Workspace $workspace, string $query, int $limit): Collection
    {
        return PrepList::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name']))
            ->with('event:id,name')
            ->select(['id', 'workspace_id', 'name', 'status', 'event_id'])
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (PrepList $prepList): array => $this->result(
                'prep',
                $prepList->id,
                $prepList->name,
                $prepList->event?->name ?? $prepList->status,
                'prep.detail',
            ));
    }

    private function tasks(Workspace $workspace, string $query, int $limit): Collection
    {
        return Task::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['title', 'description']))
            ->with('event:id,name')
            ->select(['id', 'workspace_id', 'title', 'description', 'status', 'event_id'])
            ->orderByRaw('CASE WHEN title LIKE ? THEN 0 ELSE 1 END', ["{$query}%"])
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->map(fn (Task $task): array => $this->result(
                'task',
                $task->id,
                $task->title,
                $task->event?->name ?? $task->status,
                'task.detail',
            ));
    }

    private function teams(Workspace $workspace, string $query, int $limit): Collection
    {
        return Team::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name', 'key']))
            ->select(['id', 'workspace_id', 'name', 'key', 'status'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Team $team): array => $this->result(
                'team',
                $team->id,
                $team->name,
                $team->key,
                'team.roster',
                ['status' => $team->status],
            ));
    }

    private function stations(Workspace $workspace, string $query, int $limit): Collection
    {
        return Station::query()
            ->where('workspace_id', $workspace->id)
            ->where(fn ($builder) => $this->matchColumns($builder, $query, ['name', 'key']))
            ->select(['id', 'workspace_id', 'name', 'key', 'status'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Station $station): array => $this->result(
                'station',
                $station->id,
                $station->name,
                $station->key,
                'team.roster',
                ['status' => $station->status],
            ));
    }

    private function members(Workspace $workspace, string $query, int $limit): Collection
    {
        return WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->whereHas('user', fn ($builder) => $this->matchColumns($builder, $query, ['name', 'email']))
            ->with('user:id,name')
            ->select(['id', 'workspace_id', 'user_id', 'status'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (WorkspaceMembership $membership): array => $this->result(
                'staff',
                $membership->id,
                $membership->user?->name ?? 'Staff member',
                'Workspace member',
                'team.roster',
            ));
    }

    private function matchColumns($builder, string $query, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $builder->{$method}($column, 'like', "%{$query}%");
        }
    }

    private function result(
        string $type,
        string $id,
        ?string $title,
        ?string $subtitle,
        string $target,
        array $metadata = [],
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'title' => $title ?: $id,
            'subtitle' => $subtitle,
            'metadata' => $metadata,
            'target' => $target,
        ];
    }
}
