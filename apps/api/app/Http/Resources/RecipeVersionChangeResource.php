<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeVersionChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'change_type' => $this->change_type,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'before' => $this->formatValue($this->before_value),
            'after' => $this->formatValue($this->after_value),
            'label' => $this->resolveLabel(),
            'severity' => $this->severity,
            'affects_production' => $this->affects_production,
            'reviewed' => $this->reviewed,
        ];
    }

    private function formatValue(?array $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (isset($value['label'])) {
            return (string) $value['label'];
        }

        return collect($value)
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item))
            ->implode(' · ');
    }

    private function resolveLabel(): string
    {
        return match ($this->entity_type) {
            'ingredient' => (string) ($this->after_value['ingredient_name'] ?? $this->before_value['ingredient_name'] ?? 'Ingredient'),
            'step' => (string) ($this->after_value['title'] ?? $this->before_value['title'] ?? 'Step'),
            'yield' => (string) ($this->after_value['label'] ?? $this->before_value['label'] ?? 'Yield'),
            'allergen' => (string) ($this->after_value['name'] ?? $this->before_value['name'] ?? 'Allergen'),
            default => (string) ($this->after_value['label'] ?? $this->before_value['label'] ?? 'Recipe change'),
        };
    }
}
