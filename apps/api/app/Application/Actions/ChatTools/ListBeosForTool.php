<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\BeoResource;
use App\Models\Beo;

class ListBeosForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $search = trim((string) ($filters['search'] ?? ''));
        $beos = Beo::query()->where('workspace_id', $workspaceId)
            ->with(['event', 'property', 'latestVersion.document', 'latestVersion.functions', 'references'])
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                $builder->where('event_order_number', 'like', '%'.$search.'%')
                    ->orWhere('quote_number', 'like', '%'.$search.'%')
                    ->orWhereHas('event', fn ($event) => $event->where('name', 'like', '%'.$search.'%'));
            }))
            ->latest('created_at')->limit($limit)->get();

        return [
            'count' => $beos->count(),
            'items' => BeoResource::collection($beos)->resolve(),
        ];
    }

    public function find(string $workspaceId, ?string $id = null, ?string $search = null): array
    {
        $query = Beo::query()->where('workspace_id', $workspaceId)->with([
            'event', 'property', 'latestVersion.document', 'latestVersion.functions', 'references',
        ]);
        if (trim((string) $id) !== '') {
            $beo = $query->whereKey($id)->first();
            return $beo ? ['status' => 'resolved', 'entity' => $beo] : ['status' => 'not_found'];
        }
        $term = trim((string) $search);
        if ($term === '') {
            return ['status' => 'not_found'];
        }
        $matches = $query->where(fn ($builder) => $builder->where('event_order_number', 'like', '%'.$term.'%')->orWhereHas('event', fn ($event) => $event->where('name', 'like', '%'.$term.'%')))->limit(6)->get();
        return [
            'status' => $matches->count() === 1 ? 'resolved' : ($matches->isEmpty() ? 'not_found' : 'ambiguous'),
            'entity' => $matches->count() === 1 ? $matches->first() : null,
            'candidates' => $matches->map(fn (Beo $beo): array => ['id' => $beo->id, 'name' => $beo->event_order_number ?: ($beo->event?->name ?? $beo->id)])->values()->all(),
        ];
    }
}
