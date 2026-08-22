<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $workspace = app('currentWorkspace');
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $notifications = Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('dismissed_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => NotificationResource::collection(collect($notifications->items())),
            'path' => $notifications->path(),
            'per_page' => $notifications->perPage(),
            'next_cursor' => $notifications->nextCursor()?->encode(),
            'next_page_url' => $notifications->nextPageUrl(),
            'prev_cursor' => $notifications->previousCursor()?->encode(),
            'prev_page_url' => $notifications->previousPageUrl(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $workspace = app('currentWorkspace');

        return response()->json([
            'data' => [
                'count' => Notification::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('user_id', $request->user()->id)
                    ->whereNull('read_at')
                    ->whereNull('dismissed_at')
                    ->count(),
            ],
        ]);
    }

    public function read(Request $request, Notification $notification)
    {
        $notification = $this->ownedNotification($request, $notification);

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($notification->refresh()),
        ]);
    }

    public function readAll(Request $request)
    {
        $workspace = app('currentWorkspace');

        $updated = Notification::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->whereNull('dismissed_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['updated' => $updated],
        ]);
    }

    public function preferences(Request $request)
    {
        $workspace = app('currentWorkspace');
        $stored = NotificationPreference::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('event_key', NotificationPreference::SUPPORTED_EVENT_KEYS)
            ->get()
            ->keyBy('event_key');

        return response()->json([
            'data' => collect(NotificationPreference::SUPPORTED_EVENT_KEYS)
                ->map(function (string $eventKey) use ($stored): array {
                    $preference = $stored->get($eventKey);

                    return [
                        'event_key' => $eventKey,
                        'enabled' => $preference?->enabled ?? true,
                        'in_app' => $preference?->in_app ?? true,
                        'push' => false,
                        'email' => false,
                        'minimum_priority' => $preference?->minimum_priority ?? 'all',
                        'supported_channels' => ['in_app'],
                    ];
                })
                ->values(),
            'meta' => [
                'push_available' => false,
                'email_available' => false,
            ],
        ]);
    }

    public function updatePreference(Request $request, string $eventKey)
    {
        abort_unless(
            in_array($eventKey, NotificationPreference::SUPPORTED_EVENT_KEYS, true),
            404
        );

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'in_app' => ['sometimes', 'boolean'],
            'minimum_priority' => ['sometimes', 'in', 'all,important,critical'],
        ]);
        $workspace = app('currentWorkspace');

        $preference = NotificationPreference::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'event_key' => $eventKey,
            ],
            [
                'enabled' => $validated['enabled'] ?? true,
                'in_app' => $validated['in_app'] ?? true,
                'push' => false,
                'email' => false,
                'minimum_priority' => $validated['minimum_priority'] ?? 'all',
            ]
        );

        return response()->json([
            'data' => [
                'event_key' => $preference->event_key,
                'enabled' => $preference->enabled,
                'in_app' => $preference->in_app,
                'push' => false,
                'email' => false,
                'minimum_priority' => $preference->minimum_priority,
                'supported_channels' => ['in_app'],
            ],
        ]);
    }

    private function ownedNotification(Request $request, Notification $notification): Notification
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $notification->workspace_id === $workspace->id &&
            $notification->user_id === $request->user()->id,
            404
        );

        return $notification;
    }
}
