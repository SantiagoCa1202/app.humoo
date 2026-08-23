<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Beo\CreateBeoImportBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Beo\StoreBeoImportBatchRequest;
use App\Http\Resources\BeoImportBatchResource;
use App\Http\Resources\EventFunctionResource;
use App\Http\Resources\EventOrderResource;
use App\Http\Resources\BeoVersionResource;
use App\Models\Beo;
use App\Models\BeoImportBatch;
use App\Models\BeoVersion;
use App\Models\EventFunction;
use App\Services\OperationalVisibilityService;
use Illuminate\Http\Request;

class BeoDomainController extends Controller
{
    public function batches(Request $request)
    {
        $this->authorize('viewAny', BeoImportBatch::class);
        $workspaceId = app('currentWorkspace')->id;
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        return BeoImportBatchResource::collection(
            BeoImportBatch::query()
                ->where('workspace_id', $workspaceId)
                ->with(['property', 'createdBy'])
                ->withCount('eventOrders')
                ->latest('created_at')
                ->paginate($perPage)
        );
    }

    public function storeBatch(
        StoreBeoImportBatchRequest $request,
        CreateBeoImportBatch $action
    ) {
        $this->authorize('create', BeoImportBatch::class);

        $batch = $action->execute(
            app('currentWorkspace')->id,
            $request->user()->id,
            $request->validated()
        );

        return (new BeoImportBatchResource($batch))
            ->response()
            ->setStatusCode(201);
    }

    public function showBatch(BeoImportBatch $batch)
    {
        abort_unless($batch->workspace_id === app('currentWorkspace')->id, 404);
        $this->authorize('view', $batch);

        return new BeoImportBatchResource($batch->load([
            'property', 'document', 'createdBy',
            'eventOrders.latestVersion.functions.venues',
            'eventOrders.latestVersion.functions.dietaryRequirements',
            'eventOrders.latestVersion.functions.instructions',
        ]));
    }

    public function orders(Request $request, OperationalVisibilityService $visibility)
    {
        $this->authorize('viewAny', Beo::class);
        $workspaceId = app('currentWorkspace')->id;
        $membership = app('currentMembership')->loadMissing('workspace');
        $includeHidden = $request->boolean('include_hidden');
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $orders = Beo::query()
            ->where('workspace_id', $workspaceId)
            ->with($this->domainRelations())
            ->latest('created_at')
            ->paginate($perPage);

        $orders->getCollection()->each(
            fn (Beo $order) => $this->applyVisibility($order, $membership, $visibility, $includeHidden)
        );

        return EventOrderResource::collection($orders)->additional([
            'meta' => [
                'include_hidden' => $includeHidden,
                'visibility' => $visibility->settings($membership),
            ],
        ]);
    }

    public function showOrder(
        Request $request,
        Beo $beo,
        OperationalVisibilityService $visibility
    ) {
        abort_unless($beo->workspace_id === app('currentWorkspace')->id, 404);
        $this->authorize('view', $beo);
        $membership = app('currentMembership')->loadMissing('workspace');
        $beo->load($this->domainRelations());
        $this->applyVisibility($beo, $membership, $visibility, $request->boolean('include_hidden'));

        return new EventOrderResource($beo);
    }

    public function versions(Beo $beo)
    {
        abort_unless($beo->workspace_id === app('currentWorkspace')->id, 404);
        $this->authorize('view', $beo);

        return BeoVersionResource::collection(
            $beo->versions()
                ->with([
                    'document',
                    'functions.venues',
                    'functions.dietaryRequirements',
                    'functions.instructions',
                    'references',
                ])
                ->orderByDesc('version')
                ->get()
        );
    }

    public function functions(Request $request, OperationalVisibilityService $visibility)
    {
        $this->authorize('viewAny', Beo::class);
        $membership = app('currentMembership')->loadMissing('workspace');
        $includeHidden = $request->boolean('include_hidden');
        $functions = EventFunction::query()
            ->where('workspace_id', app('currentWorkspace')->id)
            ->with(['venues', 'dietaryRequirements', 'instructions'])
            ->when($request->filled('beo_version_id'), fn ($query) => $query->where('beo_version_id', $request->input('beo_version_id')))
            ->get();

        $visible = $functions->filter(function (EventFunction $function) use ($includeHidden, $membership, $visibility): bool {
            $isVisible = $visibility->visibleTo($membership, $function, $includeHidden);
            $function->hidden_by_preferences = !$isVisible;

            return $isVisible;
        })->values();

        return EventFunctionResource::collection($visible)->additional([
            'meta' => [
                'include_hidden' => $includeHidden,
                'hidden_count' => $functions->count() - $visible->count(),
            ],
        ]);
    }

    private function domainRelations(): array
    {
        return [
            'event', 'property', 'importBatch',
            'latestVersion.functions.venues',
            'latestVersion.functions.dietaryRequirements',
            'latestVersion.functions.instructions',
            'latestVersion.references',
            'versions.functions.venues',
            'versions.functions.dietaryRequirements',
            'versions.functions.instructions',
            'versions.references',
            'references',
        ];
    }

    private function applyVisibility(
        Beo $order,
        $membership,
        OperationalVisibilityService $visibility,
        bool $includeHidden
    ): void {
        foreach ($order->versions as $version) {
            $version->setRelation(
                'functions',
                $this->filterFunctions($version->functions ?? collect(), $membership, $visibility, $includeHidden)
            );
        }

        if ($order->relationLoaded('latestVersion') && $order->latestVersion) {
            $latest = $order->latestVersion;
            $latest->setRelation(
                'functions',
                $this->filterFunctions($latest->functions ?? collect(), $membership, $visibility, $includeHidden)
            );
        }
    }

    private function filterFunctions($functions, $membership, OperationalVisibilityService $visibility, bool $includeHidden)
    {
        return $functions->filter(function (EventFunction $function) use ($includeHidden, $membership, $visibility): bool {
            $isVisible = $visibility->visibleTo($membership, $function, $includeHidden);
            $function->hidden_by_preferences = !$isVisible;

            return $isVisible;
        })->values();
    }
}
