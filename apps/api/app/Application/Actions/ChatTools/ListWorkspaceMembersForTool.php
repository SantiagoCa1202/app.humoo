<?php

namespace App\Application\Actions\ChatTools;

use App\Models\WorkspaceMembership;

class ListWorkspaceMembersForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 25), 100));
        $search = trim((string) ($filters['search'] ?? ''));
        $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [$filters['status'] ?? null];
        $statuses = collect($statuses)->filter()->values()->all();
        $members = WorkspaceMembership::query()->where('workspace_id', $workspaceId)->with(['user', 'role.permissions'])
            ->whereIn('status', ['pending', 'active', 'suspended', 'removed'])
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('created_at')->limit($limit)->get();
        return ['count' => $members->count(), 'items' => $members->map(fn (WorkspaceMembership $member): array => $this->serialize($member))->values()->all()];
    }

    public function find(string $workspaceId, ?string $id = null, ?string $search = null, array $refs = []): array
    {
        $query = WorkspaceMembership::query()->where('workspace_id', $workspaceId)->where('status', 'active')->with(['user', 'role.permissions']);
        if (trim((string) $id) !== '') {
            $member = $query->whereKey($id)->first();
            return $member ? ['status' => 'resolved', 'entity' => $member] : ['status' => 'not_found'];
        }
        if (in_array(mb_strtolower(trim((string) $search)), ['that', 'this', 'ese', 'esa'], true)) {
            $ref = collect($refs)->first(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'membership');
            $member = $query->whereKey($ref['id'] ?? null)->first();
            return $member ? ['status' => 'resolved', 'entity' => $member] : ['status' => 'not_found'];
        }
        $term = trim((string) $search);
        if ($term === '') return ['status' => 'not_found'];
        $matches = $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))->limit(6)->get();
        $candidates = $matches->map(fn (WorkspaceMembership $member): array => $this->candidate($member))->values()->all();

        // Keep the decision in the AI flow: when the search is not exact, give
        // it safe workspace-scoped choices instead of silently selecting one.
        if ($matches->isEmpty()) {
            $candidates = $query->orderBy('created_at')->limit(6)->get()
                ->map(fn (WorkspaceMembership $member): array => $this->candidate($member))->values()->all();
        }

        $exactMatches = $matches->filter(function (WorkspaceMembership $member) use ($term): bool {
            $needle = mb_strtolower($term);

            return in_array($needle, [
                mb_strtolower(trim((string) $member->user?->name)),
                mb_strtolower(trim((string) $member->user?->email)),
            ], true);
        });

        return [
            'status' => $exactMatches->count() === 1 ? 'resolved' : ($candidates !== [] ? 'ambiguous' : 'not_found'),
            'entity' => $exactMatches->count() === 1 ? $exactMatches->first() : null,
            'candidates' => $candidates,
        ];
    }

    public function serialize(WorkspaceMembership $member): array
    {
        return ['id' => $member->id, 'status' => $member->status, 'joined_at' => $member->joined_at?->toIso8601String(), 'user' => $member->user ? ['id' => $member->user->id, 'name' => $member->user->name, 'email' => $member->user->email] : null, 'role' => $member->role ? ['id' => $member->role->id, 'key' => $member->role->key, 'name' => $member->role->name] : null];
    }

    private function candidate(WorkspaceMembership $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->user?->name ?? $member->user?->email ?? $member->id,
            'safe_metadata' => array_filter([
                'email' => $member->user?->email,
                'role' => $member->role?->name,
            ], static fn (mixed $value): bool => filled($value)),
        ];
    }
}
