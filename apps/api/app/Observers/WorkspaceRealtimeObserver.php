<?php

namespace App\Observers;

use App\Events\Realtime\WorkspaceChanged;
use App\Models\Beo;
use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\Models\Event;
use App\Models\ExtractionRun;
use App\Models\Notification;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\PrepListVersion;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Model;

/**
 * Broadcasts only safe operational invalidation signals, never model data.
 */
class WorkspaceRealtimeObserver
{
    private const ENTITY_TYPES = [
        Beo::class => 'beo',
        BeoVersion::class => 'beo.processing',
        Document::class => 'document',
        DocumentProcessingJob::class => 'beo.processing',
        Event::class => 'event',
        ExtractionRun::class => 'beo.processing',
        Notification::class => 'notification',
        PrepItem::class => 'prep.item',
        PrepList::class => 'prep',
        PrepListVersion::class => 'prep',
        Task::class => 'task',
        TaskAssignment::class => 'task',
    ];

    public function created(Model $model): void
    {
        $this->broadcast($model, 'created');
    }

    public function updated(Model $model): void
    {
        if ($model instanceof Notification) {
            return;
        }

        $this->broadcast($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Notification) {
            return;
        }

        $this->broadcast($model, 'deleted');
    }

    private function broadcast(Model $model, string $operation): void
    {
        $entityType = self::ENTITY_TYPES[$model::class] ?? null;
        $workspaceId = (string) $model->getAttribute('workspace_id');

        if (!$entityType || $workspaceId === '') {
            return;
        }

        $type = match (true) {
            $model instanceof Notification => 'notification.created',
            $model instanceof BeoVersion,
            $model instanceof DocumentProcessingJob,
            $model instanceof ExtractionRun => 'beo.processing.updated',
            $model instanceof PrepItem => 'prep.item.updated',
            $model instanceof TaskAssignment => 'task.updated',
            default => "{$entityType}.{$operation}",
        };

        WorkspaceChanged::dispatch(
            type: $type,
            workspaceId: $workspaceId,
            entityType: $entityType,
            entityId: (string) $model->getKey(),
            version: is_numeric($model->getAttribute('version'))
                ? (int) $model->getAttribute('version')
                : null,
        );
    }
}
