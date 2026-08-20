<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Prep\CreatePrepList;
use App\Application\Actions\Prep\GeneratePrepList;
use App\Application\Actions\Prep\UpdatePrepList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prep\GeneratePrepListRequest;
use App\Http\Requests\Prep\StorePrepListRequest;
use App\Http\Requests\Prep\UpdatePrepListRequest;
use App\Http\Resources\PrepListResource;
use App\Http\Resources\PrepListVersionResource;
use App\Models\PrepList;
use App\Models\PrepListVersion;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class PrepListController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PrepList::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $eventId = trim((string) $request->input('event_id', ''));
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $prepLists = PrepList::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->prepListRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($eventId !== '', fn ($query) => $query->where('event_id', $eventId))
            ->latest('updated_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => PrepListResource::collection(collect($prepLists->items())),
            'path' => $prepLists->path(),
            'per_page' => $prepLists->perPage(),
            'next_cursor' => $prepLists->nextCursor()?->encode(),
            'next_page_url' => $prepLists->nextPageUrl(),
            'prev_cursor' => $prepLists->previousCursor()?->encode(),
            'prev_page_url' => $prepLists->previousPageUrl(),
        ]);
    }

    public function store(
        StorePrepListRequest $request,
        CreatePrepList $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', PrepList::class);

        $workspace = app('currentWorkspace');
        $prepList = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );
        $prepList = $this->loadPrepList($prepList);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'prep_list.created',
            PrepList::class,
            $prepList->id,
            null,
            $prepList->toArray()
        );

        return response()->json([
            'data' => new PrepListResource($prepList),
        ], 201);
    }

    public function show(PrepList $prepList)
    {
        $workspace = app('currentWorkspace');

        abort_unless($prepList->workspace_id === $workspace->id, 404);
        $this->authorize('view', $prepList);

        return response()->json([
            'data' => new PrepListResource($this->loadPrepList($prepList)),
        ]);
    }

    public function update(
        UpdatePrepListRequest $request,
        PrepList $prepList,
        UpdatePrepList $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($prepList->workspace_id === $workspace->id, 404);
        $this->authorize('update', $prepList);

        $before = $prepList->toArray();
        $updated = $action->execute(
            $prepList,
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );
        $updated = $this->loadPrepList($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'prep_list.updated',
            PrepList::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new PrepListResource($updated),
        ]);
    }

    public function generate(
        GeneratePrepListRequest $request,
        PrepList $prepList,
        GeneratePrepList $action,
        AuditLogger $auditLogger
    ) {
        return $this->handleGeneration(
            $request,
            $prepList,
            $action,
            $auditLogger,
            false
        );
    }

    public function regenerate(
        GeneratePrepListRequest $request,
        PrepList $prepList,
        GeneratePrepList $action,
        AuditLogger $auditLogger
    ) {
        return $this->handleGeneration(
            $request,
            $prepList,
            $action,
            $auditLogger,
            true
        );
    }

    public function versions(PrepList $prepList)
    {
        $workspace = app('currentWorkspace');

        abort_unless($prepList->workspace_id === $workspace->id, 404);
        $this->authorize('view', $prepList);

        $versions = PrepListVersion::query()
            ->where('workspace_id', $workspace->id)
            ->where('prep_list_id', $prepList->id)
            ->with($this->versionRelations())
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => PrepListVersionResource::collection($versions),
        ]);
    }

    private function handleGeneration(
        GeneratePrepListRequest $request,
        PrepList $prepList,
        GeneratePrepList $action,
        AuditLogger $auditLogger,
        bool $isRegeneration
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($prepList->workspace_id === $workspace->id, 404);
        $this->authorize('update', $prepList);

        $preview = $request->boolean('preview');
        $before = $prepList->toArray();
        $result = $action->execute(
            $prepList,
            $workspace->id,
            $request->user()?->id,
            $request->validated(),
            !$preview
        );

        if (!$preview && isset($result['prep_list']) && $result['prep_list'] instanceof PrepList) {
            $actionName = $isRegeneration ? 'prep_list.regenerated' : 'prep_list.generated';

            $auditLogger->logWorkspaceAction(
                $request,
                $workspace->id,
                $request->user()?->id,
                $actionName,
                PrepList::class,
                $prepList->id,
                $before,
                $result['prep_list']->toArray()
            );
        }

        return response()->json([
            'data' => $this->buildGenerationPayload($result),
        ], $preview ? 200 : 201);
    }

    private function buildGenerationPayload(array $result): array
    {
        $prepList = $result['prep_list'] ?? null;
        $version = $result['version'] ?? $result['version_preview'] ?? null;

        return [
            'estimated_assignments' => $result['estimated_assignments'] ?? null,
            'estimated_items' => $result['estimated_items'] ?? null,
            'event' => $result['event']
                ? [
                    'id' => $result['event']->id,
                    'name' => $result['event']->name,
                    'starts_at' => $result['event']->starts_at?->toIso8601String(),
                    'ends_at' => $result['event']->ends_at?->toIso8601String(),
                    'timezone' => $result['event']->timezone,
                ]
                : null,
            'items' => $result['items'] ?? [],
            'menu_label' => $result['menu_label'] ?? null,
            'prep_list' => $prepList instanceof PrepList
                ? (new PrepListResource($this->loadPrepList($prepList)))->resolve()
                : ($prepList ? (new PrepListResource($this->loadPrepList($prepList)))->resolve() : null),
            'progress' => $result['progress'] ?? null,
            'summary' => $result['summary'] ?? null,
            'version' => $version instanceof PrepListVersion
                ? (new PrepListVersionResource($version))->resolve()
                : $version,
            'warnings' => array_values($result['warnings'] ?? []),
        ];
    }

    private function prepListRelations(): array
    {
        return [
            'completedBy',
            'createdBy',
            'currentVersionRecord.approvedBy',
            'currentVersionRecord.createdBy',
            'currentVersionRecord.lockedBy',
            'currentVersionRecord.menuVersion',
            'currentVersionRecord.sections.items.assignments',
            'event',
            'updatedBy',
        ];
    }

    private function versionRelations(): array
    {
        return [
            'approvedBy',
            'createdBy',
            'lockedBy',
            'menuVersion',
            'sections.items.assignments.assignedBy',
            'sections.items.assignments.membership.role',
            'sections.items.assignments.membership.teams',
            'sections.items.assignments.membership.user',
            'sections.items.actualUnit',
            'sections.items.completedBy',
            'sections.items.createdBy',
            'sections.items.recipe',
            'sections.items.recipeVersion',
            'sections.items.unit',
            'sections.items.updatedBy',
            'sections.items.yieldUnit',
        ];
    }

    private function loadPrepList(PrepList $prepList): PrepList
    {
        return PrepList::query()
            ->whereKey($prepList->getKey())
            ->where('workspace_id', $prepList->workspace_id)
            ->with(array_merge($this->prepListRelations(), [
                'currentVersionRecord.sections.items.assignments.assignedBy',
                'currentVersionRecord.sections.items.assignments.membership.role',
                'currentVersionRecord.sections.items.assignments.membership.teams',
                'currentVersionRecord.sections.items.assignments.membership.user',
                'currentVersionRecord.sections.items.actualUnit',
                'currentVersionRecord.sections.items.completedBy',
                'currentVersionRecord.sections.items.createdBy',
                'currentVersionRecord.sections.items.recipe',
                'currentVersionRecord.sections.items.recipeVersion',
                'currentVersionRecord.sections.items.unit',
                'currentVersionRecord.sections.items.updatedBy',
                'currentVersionRecord.sections.items.yieldUnit',
                'versions.createdBy',
                'versions.menuVersion',
            ]))
            ->firstOrFail();
    }
}
