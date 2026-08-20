<?php

namespace App\Http\Resources;

use App\Models\Shift;
use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftConflictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = is_array($this->details) ? $this->details : [];
        $relatedShift = null;

        if (
            isset($details['related_shift'])
            && is_array($details['related_shift'])
        ) {
            $relatedShift = $details['related_shift'];
        }

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'membership_id' => $this->membership_id,
            'shift_id' => $this->shift_id,
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'details' => $details,
            'resolved' => $this->resolved,
            'required_staff' => $details['required_staff'] ?? null,
            'assigned_staff' => $details['assigned_staff'] ?? null,
            'member' => $this->relationLoaded('membership') && $this->membership
                ? (new WorkspaceMemberReferenceResource($this->membership))->resolve()
                : null,
            'shift' => $this->relationLoaded('shift') && $this->shift
                ? (new ShiftResource($this->shift))->resolve()
                : null,
            'related_shift' => $relatedShift,
            'station' => isset($details['station']) && is_array($details['station'])
                ? $details['station']
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
