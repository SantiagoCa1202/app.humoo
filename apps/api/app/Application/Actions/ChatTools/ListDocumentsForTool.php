<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\DocumentResource;
use App\Models\Document;

class ListDocumentsForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['processing_status'] ?? $filters['status'] ?? ''));

        $documents = Document::query()
            ->where('workspace_id', $workspaceId)
            ->with(['uploadedBy', 'updatedBy', 'links', 'latestBeoVersion', 'latestExtractionRun'])
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('original_filename', 'like', '%'.$search.'%');
            }))
            ->when($status !== '', fn ($query) => $query->where('processing_status', $status))
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return [
            'count' => $documents->count(),
            'items' => DocumentResource::collection($documents)->resolve(),
        ];
    }

    public function find(string $workspaceId, ?string $id = null, ?string $search = null, array $refs = []): array
    {
        $resolvedId = trim((string) $id);
        if ($resolvedId === '' && in_array(mb_strtolower(trim((string) $search)), ['that', 'this', 'ese', 'esa'], true)) {
            $ref = collect($refs)->first(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'document');
            $resolvedId = (string) ($ref['id'] ?? '');
        }

        $query = Document::query()->where('workspace_id', $workspaceId)->with([
            'uploadedBy', 'updatedBy', 'links', 'latestBeoVersion.beo', 'latestExtractionRun.fields',
        ]);
        if ($resolvedId !== '') {
            $document = $query->whereKey($resolvedId)->first();
            return $document ? ['status' => 'resolved', 'entity' => $document] : ['status' => 'not_found'];
        }

        $term = trim((string) $search);
        if ($term === '') {
            return ['status' => 'not_found'];
        }
        $matches = $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$term.'%')->orWhere('original_filename', 'like', '%'.$term.'%'))
            ->limit(6)->get();

        return [
            'status' => $matches->count() === 1 ? 'resolved' : ($matches->isEmpty() ? 'not_found' : 'ambiguous'),
            'entity' => $matches->count() === 1 ? $matches->first() : null,
            'candidates' => $matches->map(fn (Document $document): array => ['id' => $document->id, 'name' => $document->name])->values()->all(),
        ];
    }
}
