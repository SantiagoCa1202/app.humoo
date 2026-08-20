<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'dimension' => $this->dimension,
            'decimal_places' => $this->decimal_places,
        ];
    }
}
