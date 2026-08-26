<?php

namespace App\AI\Advisory;

use Illuminate\Support\Arr;

/**
 * Produces only deterministic aggregates. It intentionally does not infer
 * waste or shortages: current operational data does not model those outcomes.
 */
class PortionAnalysisService
{
    public function analyze(array $toolResults, string $analysisType): array
    {
        $events = $this->itemsFor($toolResults, 'events.list');
        $menus = $this->itemsFor($toolResults, 'menus.search');
        $menus = [...$menus, ...$this->itemsFor($toolResults, 'menus.show')];
        $prepLists = $this->itemsFor($toolResults, 'prep.list');

        $guestCounts = collect($events)
            ->map(fn (array $event) => $event['guest_count_confirmed'] ?? $event['guest_count_expected'] ?? null)
            ->filter(fn (mixed $count): bool => is_numeric($count) && (int) $count > 0)
            ->map(fn (mixed $count): int => (int) $count)
            ->values();

        $menuRows = collect($menus)->map(function (array $menu): array {
            $guestCount = is_numeric($menu['guest_count'] ?? null)
                ? (int) $menu['guest_count']
                : (is_numeric($menu['default_guest_count'] ?? null) ? (int) $menu['default_guest_count'] : null);
            $items = collect($menu['sections'] ?? [])
                ->filter(fn (mixed $section): bool => is_array($section))
                ->flatMap(fn (array $section) => is_array($section['items'] ?? null) ? $section['items'] : [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(function (array $item) use ($guestCount): array {
                    $perGuest = is_numeric($item['quantity_per_guest'] ?? null)
                        ? (float) $item['quantity_per_guest']
                        : null;

                    return array_filter([
                        'id' => $item['id'] ?? null,
                        'name' => $item['name'] ?? null,
                        'planned_quantity' => is_numeric($item['planned_quantity'] ?? null) ? (float) $item['planned_quantity'] : null,
                        'quantity_per_guest' => $perGuest,
                        'serving_unit' => $item['serving_unit'] ?? null,
                        'deterministic_planned_total' => $perGuest !== null && $guestCount !== null
                            ? round($perGuest * $guestCount, 4)
                            : null,
                    ], static fn (mixed $value): bool => $value !== null && $value !== '');
                })->values()->all();

            return array_filter([
                'id' => $menu['id'] ?? null,
                'name' => $menu['name'] ?? null,
                'guest_count' => $guestCount,
                'items' => $items,
                'menu_structure_locked' => (bool) Arr::get($menu, 'metadata.menu_structure_locked', false),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        })->values()->all();

        $prepared = collect($prepLists)->flatMap(function (array $entry): array {
            $prep = is_array($entry['prep_list'] ?? null) ? $entry['prep_list'] : $entry;
            $sections = Arr::get($prep, 'current_version_record.sections', []);

            return collect(is_array($sections) ? $sections : [])
                ->flatMap(fn (array $section) => is_array($section['items'] ?? null) ? $section['items'] : [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->map(fn (array $item): array => array_filter([
                    'title' => $item['title'] ?? null,
                    'planned_quantity' => is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : null,
                    'actual_quantity' => is_numeric($item['actual_quantity'] ?? null) ? (float) $item['actual_quantity'] : null,
                    'unit' => $item['unit_label'] ?? Arr::get($item, 'unit.name'),
                    'status' => $item['status'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''))
                ->all();
        })->values()->all();

        return [
            'analysis_type' => $analysisType,
            'facts' => [
                'event_count' => count($events),
                'total_guests' => $guestCounts->sum(),
                'average_guest_count' => $guestCounts->isNotEmpty() ? round($guestCounts->avg(), 2) : null,
                'menus' => $menuRows,
                'prep_items' => $prepared,
            ],
            'calculations' => [
                'menu_item_totals_are_quantity_per_guest_times_guest_count_when_both_values_exist' => true,
                'prepared_item_count' => count($prepared),
            ],
            'warnings' => [
                'Outcome signals such as leftovers, shortages, waste, and actual used are not modeled by this analysis. Do not make categorical overproduction or underproduction claims from planned quantities alone.',
            ],
        ];
    }

    private function itemsFor(array $results, string $key): array
    {
        return collect($results[$key]['result_ref_json']['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
    }
}
