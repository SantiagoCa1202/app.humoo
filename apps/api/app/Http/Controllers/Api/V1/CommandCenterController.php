<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeoResource;
use App\Http\Resources\BeoVersionResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PrepListResource;
use App\Http\Resources\TaskResource;
use App\Models\Availability;
use App\Models\Document;
use App\Models\Event;
use App\Models\Menu;
use App\Models\PrepList;
use App\Models\Recipe;
use App\Models\Shift;
use App\Models\Task;
use App\Models\WorkspaceMembership;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CommandCenterController extends Controller
{
    public function __invoke(Request $request)
    {
        $workspace = app('currentWorkspace');
        $membership = app('currentMembership');
        $user = $request->user();
        $workspaceTimezone = $workspace->timezone ?: 'UTC';
        $workspaceNow = CarbonImmutable::now($workspaceTimezone);
        $permissions = $this->resolvePermissions($user, $workspace->id);

        $upcomingEvents = collect();
        $eventsTodayCount = null;

        if ($permissions['events']) {
            $upcomingEvents = $this->loadUpcomingEvents($workspace->id);
            $eventsTodayCount = Event::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereBetween('starts_at', [
                    $workspaceNow->startOfDay()->utc(),
                    $workspaceNow->endOfDay()->utc(),
                ])
                ->count();
        }

        $activePrepCount = null;
        $activePrepList = null;
        $prepProgress = null;

        if ($permissions['prep']) {
            $activePrepCount = PrepList::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'in_progress'])
                ->count();

            $activePrepList = $this->loadPrimaryPrepList($workspace->id);
            $prepProgress = $activePrepList ? $this->buildPrepProgress($activePrepList) : null;
        }

        $openTasksCount = null;
        $myTasks = collect();
        $taskSummary = null;
        $overdueTasksCount = null;

        if ($permissions['tasks']) {
            $openTasksCount = Task::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotIn('status', ['done', 'cancelled'])
                ->count();
            $taskSummary = $this->buildTaskSummary($workspace->id);
            $overdueTasksCount = $taskSummary['overdue'] ?? 0;
            $myTasks = $membership
                ? $this->loadMyTasks($workspace->id, $membership->id)
                : collect();
        }

        $staffingSummary = null;
        $teamMembersCount = null;

        if ($permissions['staff']) {
            $staffingSummary = $this->buildStaffingSummary($workspace->id);
            $teamMembersCount = $staffingSummary['total'] ?? 0;
        }

        $menusCount = $permissions['menus']
            ? Menu::query()
                ->where('workspace_id', $workspace->id)
                ->count()
            : null;

        $recipesCount = $permissions['recipes']
            ? Recipe::query()
                ->where(function ($query) use ($workspace): void {
                    $query->whereNull('workspace_id')
                        ->orWhere('workspace_id', $workspace->id);
                })
                ->count()
            : null;

        $beoAttentionItems = $permissions['documents']
            ? $this->loadBeoAttentionItems($workspace->id)
            : collect();

        return response()->json([
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'workspace' => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'timezone' => $workspaceTimezone,
                ],
                'workspace_summary' => [
                    'events_today' => $eventsTodayCount,
                    'active_prep_lists' => $activePrepCount,
                    'open_tasks' => $openTasksCount,
                    'team_members' => $teamMembersCount,
                    'menus' => $menusCount,
                    'recipes' => $recipesCount,
                ],
                'upcoming_events' => EventResource::collection($upcomingEvents)->resolve(),
                'active_prep' => $activePrepList
                    ? (new PrepListResource($activePrepList))->resolve()
                    : null,
                'prep_progress' => $prepProgress,
                'my_tasks' => TaskResource::collection($myTasks)->resolve(),
                'task_summary' => $taskSummary,
                'staffing_summary' => $staffingSummary,
                'beo_attention_items' => $beoAttentionItems->values()->all(),
                'attention_items' => $this->buildAttentionItems(
                    $overdueTasksCount,
                    $prepProgress['blocked'] ?? null
                ),
            ],
        ]);
    }

    private function resolvePermissions($user, string $workspaceId): array
    {
        return [
            'documents' => $user->hasWorkspacePermission($workspaceId, 'events.view'),
            'events' => $user->hasWorkspacePermission($workspaceId, 'events.view'),
            'menus' => $user->hasWorkspacePermission($workspaceId, 'menus.view'),
            'prep' => $user->hasWorkspacePermission($workspaceId, 'prep_lists.view'),
            'recipes' => $user->hasWorkspacePermission($workspaceId, 'recipes.view'),
            'staff' => $user->hasWorkspacePermission($workspaceId, 'members.view'),
            'tasks' => $user->hasWorkspacePermission($workspaceId, 'tasks.view'),
        ];
    }

    private function loadUpcomingEvents(string $workspaceId): Collection
    {
        return Event::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
            ->with($this->eventRelations())
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
    }

    private function loadPrimaryPrepList(string $workspaceId): ?PrepList
    {
        return PrepList::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['active', 'in_progress', 'review', 'approved'])
            ->with($this->prepListRelations())
            ->orderByRaw("case when status in ('active', 'in_progress') then 0 else 1 end")
            ->orderByRaw('production_starts_at is null')
            ->orderBy('production_starts_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function buildPrepProgress(PrepList $prepList): ?array
    {
        $items = $prepList->currentVersionRecord?->sections
            ? $prepList->currentVersionRecord->sections
                ->flatMap(fn ($section) => $section->items ?? collect())
            : collect();

        if ($items->isEmpty()) {
            return null;
        }

        $done = $items->where('status', 'done')->count();
        $inProgress = $items->where('status', 'in_progress')->count();
        $blocked = $items->where('status', 'blocked')->count();
        $skipped = $items->where('status', 'skipped')->count();
        $total = $items->count();

        return [
            'blocked' => $blocked,
            'done' => $done,
            'in_progress' => $inProgress,
            'skipped' => $skipped,
            'todo' => max($total - $done - $inProgress - $blocked - $skipped, 0),
            'total' => $total,
        ];
    }

    private function loadMyTasks(string $workspaceId, string $membershipId): Collection
    {
        return Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereHas('assignments', function ($query) use ($membershipId): void {
                $query->where('membership_id', $membershipId);
            })
            ->with($this->taskRelations())
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderByDesc('updated_at')
            ->limit(6)
            ->get();
    }

    private function buildTaskSummary(string $workspaceId): array
    {
        $baseQuery = Task::query()
            ->where('workspace_id', $workspaceId);

        return [
            'assigned' => (clone $baseQuery)
                ->whereHas('assignments')
                ->count(),
            'blocked' => (clone $baseQuery)
                ->where('status', 'blocked')
                ->count(),
            'cancelled' => (clone $baseQuery)
                ->where('status', 'cancelled')
                ->count(),
            'done' => (clone $baseQuery)
                ->where('status', 'done')
                ->count(),
            'in_progress' => (clone $baseQuery)
                ->where('status', 'in_progress')
                ->count(),
            'overdue' => (clone $baseQuery)
                ->whereNotNull('due_at')
                ->whereNotIn('status', ['done', 'cancelled'])
                ->where('due_at', '<', now())
                ->count(),
            'todo' => (clone $baseQuery)
                ->where('status', 'todo')
                ->count(),
            'total' => (clone $baseQuery)->count(),
            'unassigned' => (clone $baseQuery)
                ->doesntHave('assignments')
                ->count(),
        ];
    }

    private function buildStaffingSummary(string $workspaceId): array
    {
        $memberships = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['active', 'pending'])
            ->get();
        $activeShiftMembershipIds = Shift::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->pluck('membership_id')
            ->filter()
            ->unique();
        $currentAvailability = Availability::query()
            ->where('workspace_id', $workspaceId)
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderByDesc('starts_at')
            ->get()
            ->groupBy('membership_id')
            ->map(fn (Collection $records) => $records->first());

        $summary = [
            'active' => 0,
            'available' => 0,
            'invited' => 0,
            'on_shift' => 0,
            'total' => $memberships->count(),
            'unavailable' => 0,
        ];

        foreach ($memberships as $membership) {
            if ($membership->status === 'active') {
                $summary['active']++;
            }

            if ($membership->status === 'pending') {
                $summary['invited']++;
            }

            $isOnShift = $activeShiftMembershipIds->contains($membership->id);

            if ($isOnShift) {
                $summary['on_shift']++;
                $summary['available']++;
                continue;
            }

            $availabilityStatus = $this->normalizeAvailabilityStatus(
                $currentAvailability->get($membership->id)
            );

            if ($availabilityStatus === 'available') {
                $summary['available']++;
            }

            if (in_array($availabilityStatus, ['unavailable', 'away', 'busy'], true)) {
                $summary['unavailable']++;
            }
        }

        return $summary;
    }

    private function loadBeoAttentionItems(string $workspaceId): Collection
    {
        $documents = Document::query()
            ->where('workspace_id', $workspaceId)
            ->where('type', 'beo')
            ->with([
                'links',
                'latestBeoVersion.approvedBy',
                'latestBeoVersion.beo.event.client.primaryContact',
                'latestBeoVersion.beo.event.contact.client',
                'latestBeoVersion.beo.event.group',
                'latestBeoVersion.beo.event.venue',
                'latestBeoVersion.createdBy',
                'latestExtractionRun.requestedBy',
                'uploadedBy',
                'updatedBy',
            ])
            ->latest('updated_at')
            ->limit(12)
            ->get();

        return $documents
            ->map(fn (Document $document) => $this->buildBeoAttentionItem($document))
            ->filter()
            ->sortBy(fn (array $item) => $this->attentionPriority($item['reason']))
            ->take(4)
            ->values();
    }

    private function buildBeoAttentionItem(Document $document): ?array
    {
        $reason = $this->resolveDocumentAttentionReason($document);

        if ($reason === null) {
            return null;
        }

        $beo = $document->latestBeoVersion?->beo;
        $version = $document->latestBeoVersion;

        return [
            'beo' => $beo ? (new BeoResource($beo))->resolve() : null,
            'document' => (new DocumentResource($document))->resolve(),
            'message' => $this->documentAttentionMessage($reason),
            'reason' => $reason,
            'tone' => $this->documentAttentionTone($reason),
            'updated_at' => $document->updated_at?->toIso8601String(),
            'version' => $version ? (new BeoVersionResource($version))->resolve() : null,
        ];
    }

    private function resolveDocumentAttentionReason(Document $document): ?string
    {
        $processingStatus = $document->processing_status;
        $versionStatus = $document->latestBeoVersion?->status;
        $extractionStatus = $document->latestExtractionRun?->status;

        if ($processingStatus === 'failed' || $extractionStatus === 'failed') {
            return 'processing_failed';
        }

        if ($versionStatus === 'review_required' || $extractionStatus === 'review_required') {
            return 'review_required';
        }

        if (in_array($processingStatus, ['uploaded', 'processing'], true)
            || in_array($extractionStatus, ['pending', 'processing'], true)) {
            return 'processing';
        }

        return null;
    }

    private function documentAttentionTone(string $reason): string
    {
        return match ($reason) {
            'processing_failed' => 'danger',
            'review_required' => 'warning',
            default => 'info',
        };
    }

    private function documentAttentionMessage(string $reason): string
    {
        return match ($reason) {
            'processing_failed' => 'processing_failed',
            'review_required' => 'review_required',
            default => 'processing',
        };
    }

    private function attentionPriority(string $reason): int
    {
        return match ($reason) {
            'processing_failed' => 0,
            'review_required' => 1,
            default => 2,
        };
    }

    private function buildAttentionItems(?int $overdueTasksCount, ?int $blockedPrepCount): array
    {
        return collect([
            $overdueTasksCount && $overdueTasksCount > 0
                ? [
                    'count' => $overdueTasksCount,
                    'tone' => 'danger',
                    'type' => 'tasks_overdue',
                ]
                : null,
            $blockedPrepCount && $blockedPrepCount > 0
                ? [
                    'count' => $blockedPrepCount,
                    'tone' => 'warning',
                    'type' => 'prep_blocked',
                ]
                : null,
        ])->filter()->values()->all();
    }

    private function normalizeAvailabilityStatus(?Availability $availability): ?string
    {
        if (!$availability) {
            return null;
        }

        $type = trim((string) ($availability->type ?? ''));

        return match ($type) {
            'available' => 'available',
            'preferred' => 'away',
            'time_off', 'unavailable' => 'unavailable',
            default => $availability->available === true
                ? 'available'
                : ($availability->available === false ? 'unavailable' : null),
        };
    }

    private function eventRelations(): array
    {
        return [
            'client.primaryContact',
            'contact.client',
            'group',
            'venue',
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
            'event',
            'updatedBy',
        ];
    }

    private function taskRelations(): array
    {
        return [
            'assignments.assignedBy',
            'assignments.membership.role',
            'assignments.membership.user',
            'completedBy',
            'createdBy',
            'event',
            'station.team',
            'team',
            'updatedBy',
        ];
    }
}
