<?php

namespace App\Application\Actions\ChatTools;

use App\AI\EntityResolution\PrepEntityResolver;
use App\Http\Resources\PrepItemResource;
use App\Models\PrepItem;

class ListPrepItemsForTool
{
    public function __construct(private PrepEntityResolver $resolver)
    {
    }

    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $prepListId = trim((string) ($filters['prep_list_id'] ?? ''));
        $search = trim((string) ($filters['search'] ?? $filters['prep_item_search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $items = PrepItem::query()
            ->where('workspace_id', $workspaceId)
            ->when($prepListId !== '', fn ($builder) => $builder->whereHas(
                'section.version',
                fn ($version) => $version->where('prep_list_id', $prepListId)
            ))
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($search !== '', fn ($builder) => $builder->whereRaw('LOWER(title) like ?', ['%'.mb_strtolower($search).'%']))
            ->with([
                ...$this->resolver->itemRelations(),
            ])
            ->orderByRaw("case when status in ('done', 'skipped') then 1 else 0 end")
            ->orderBy('due_at')
            ->orderBy('position')
            ->limit($limit)
            ->get();

        return [
            'count' => $items->count(),
            'items' => $items->map(fn (PrepItem $item): array => (new PrepItemResource($item))->resolve())->values()->all(),
        ];
    }
}
