<?php

namespace App\Http\Resources;

use App\AI\Runtime\AiRunLifecycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(AiRunLifecycle::class)->snapshot($this->resource);
    }
}
