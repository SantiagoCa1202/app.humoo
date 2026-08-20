<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeoVersionChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $before = $this->before_value;
        $after = $this->after_value;

        return [
            'id' => $this->id,
            'field_key' => $this->field_key,
            'section_id' => str_contains($this->field_key, '.')
                ? explode('.', $this->field_key)[0]
                : 'general',
            'section_title' => null,
            'label' => null,
            'translation_key' => "documents.changes.fields.{$this->field_key}.label",
            'previous_value' => $this->unwrapValue($before),
            'next_value' => $this->unwrapValue($after),
            'change_type' => $this->resolveChangeType($before, $after),
            'impact' => $this->affects_production
                ? 'production'
                : null,
            'confidence' => null,
            'value_type' => null,
        ];
    }

    private function resolveChangeType(mixed $before, mixed $after): string
    {
        if ($before === null && $after !== null) {
            return 'added';
        }

        if ($before !== null && $after === null) {
            return 'removed';
        }

        if ($before === $after) {
            return 'unchanged';
        }

        return 'changed';
    }

    private function unwrapValue(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('value', $value) && count($value) === 1) {
            return $value['value'];
        }

        return $value;
    }
}
