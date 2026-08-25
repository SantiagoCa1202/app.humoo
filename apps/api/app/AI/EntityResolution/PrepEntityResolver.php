<?php

namespace App\AI\EntityResolution;

use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PrepEntityResolver
{
    public function resolveList(
        string $workspaceId,
        array $references,
        ?string $prepListId = null,
        ?string $search = null,
        ?string $eventId = null
    ): array {
        $query = PrepList::query()->where('workspace_id', $workspaceId)->with($this->listRelations());
        if (filled($prepListId)) {
            $list = $query->whereKey($prepListId)->first();
            return $list ? ['status' => 'resolved', 'prep_list' => $list] : ['status' => 'missing'];
        }
        if (filled($eventId)) {
            $matches = (clone $query)->where('event_id', $eventId)->latest('updated_at')->limit(5)->get();
            return $this->collectionResult($matches, 'prep_list');
        }
        $term = $this->normalize($search);
        if ($term !== '') {
            $matches = (clone $query)->whereRaw('LOWER(name) like ?', ["%{$term}%"])->limit(5)->get();
            return $this->collectionResult($matches, 'prep_list');
        }
        $reference = collect($references)->first(fn (array $ref): bool =>
            ($ref['type'] ?? null) === 'prep_list'
            && in_array(($ref['role'] ?? null), ['active', 'recent', 'previous'], true)
        );
        if (is_array($reference) && filled($reference['id'] ?? null)) {
            $list = (clone $query)->whereKey($reference['id'])->first();
            return $list ? ['status' => 'resolved', 'prep_list' => $list] : ['status' => 'missing'];
        }
        return ['status' => 'missing'];
    }

    public function resolveItem(
        string $workspaceId,
        array $references,
        ?string $itemId = null,
        ?string $search = null,
        ?string $prepListId = null
    ): array {
        $query = PrepItem::query()->where('workspace_id', $workspaceId)->with($this->itemRelations());
        if (filled($itemId)) {
            $item = $query->whereKey($itemId)->first();
            return $item ? ['status' => 'resolved', 'item' => $item] : ['status' => 'missing'];
        }
        $reference = collect($references)->first(fn (array $ref): bool =>
            ($ref['type'] ?? null) === 'prep_item'
            && in_array(($ref['role'] ?? null), ['active', 'recent', 'previous'], true)
        );
        if (is_array($reference) && filled($reference['id'] ?? null) && $this->normalize($search) === '') {
            $item = (clone $query)->whereKey($reference['id'])->first();
            return $item ? ['status' => 'resolved', 'item' => $item] : ['status' => 'missing'];
        }
        $term = $this->normalize($search);
        if ($term === '') {
            return ['status' => 'missing'];
        }
        $matches = (clone $query)
            ->when(filled($prepListId), fn ($builder) => $builder->whereHas('section.version', fn ($version) => $version->where('prep_list_id', $prepListId)))
            ->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(title) = ?', [$term])->orWhereRaw('LOWER(title) like ?', ["%{$term}%"]);
            })
            ->limit(6)
            ->get();
        return $this->collectionResult($matches, 'item');
    }

    public function resolveMembership(string $workspaceId, array $references, ?string $membershipId = null, ?string $search = null): array
    {
        if (filled($membershipId)) {
            $membership = WorkspaceMembership::query()->where('workspace_id', $workspaceId)->where('status', 'active')->with('user')->find($membershipId);
            return $membership ? ['status' => 'resolved', 'membership' => $membership] : ['status' => 'missing'];
        }
        $term = $this->normalize($search);
        if ($term === '') {
            return ['status' => 'missing'];
        }
        $matches = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)->where('status', 'active')->with('user')
            ->whereHas('user', fn ($user) => $user->whereRaw('LOWER(name) like ?', ["%{$term}%"]))
            ->limit(6)->get();
        return $this->collectionResult($matches, 'membership');
    }

    public function listRelations(): array
    {
        return [
            'event', 'createdBy', 'updatedBy',
            'currentVersionRecord.menuVersion',
            'currentVersionRecord.sections.items.assignments.membership.user',
            'currentVersionRecord.sections.items.assignments.membership.role',
            'currentVersionRecord.sections.items.recipe', 'currentVersionRecord.sections.items.recipeVersion',
        ];
    }

    public function itemRelations(): array
    {
        return [
            'section.version.prepList.event', 'assignments.membership.user', 'assignments.membership.role',
            'assignments.assignedBy', 'actualUnit', 'unit', 'yieldUnit', 'recipe', 'recipeVersion',
        ];
    }

    private function collectionResult(Collection $matches, string $key): array
    {
        if ($matches->count() === 1) return ['status' => 'resolved', $key => $matches->first()];
        if ($matches->count() > 1) return [
            'status' => 'ambiguous',
            'candidates' => $matches->map(fn ($model): array => [
                'id' => $model->id,
                'name' => $model instanceof PrepItem ? $model->title : ($model->name ?? $model->user?->name),
            ])->values()->all(),
        ];
        return ['status' => 'missing'];
    }

    private function normalize(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }
}
