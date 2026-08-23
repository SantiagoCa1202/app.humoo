<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class EventOrderResource extends BeoResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
