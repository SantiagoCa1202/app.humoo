<?php

namespace App\AI\Tools;

use Illuminate\Support\Str;

final class ToolProfileSelector
{
    /**
     * Select a bounded, non-dead-end view of the canonical registry. The
     * complete registry remains the safe fallback for ambiguous requests.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $metadata
     * @return array{profile:string, metadata:array<int, array<string, mixed>>}
     */
    public function select(array $context, array $metadata): array
    {
        if (!(bool) config('ai.tool_profiles.enabled', true) || $metadata === []) {
            return ['profile' => 'all', 'metadata' => $metadata];
        }

        $message = Str::lower(trim((string) ($context['message'] ?? '')));
        if ($message === '' || preg_match('/\b(?:and|y|tambien|also|everything|todo|all|entre|across|cross)\b/iu', $message) === 1) {
            return ['profile' => 'all', 'metadata' => $metadata];
        }

        $modules = [];
        foreach ([
            'recipes' => ['recipe', 'receta', 'ingredient', 'ingrediente'],
            'menus' => ['menu', 'men[uú]'],
            'prep' => ['prep', 'production', 'produccion', 'producción'],
            'tasks' => ['task', 'tarea', 'todo'],
            'events' => ['event', 'evento', 'client', 'cliente', 'contact', 'venue'],
            'team_staff' => ['team', 'equipo', 'station', 'estacion', 'turno', 'shift', 'availability', 'disponibilidad', 'member', 'miembro'],
            'documents' => ['document', 'documento', 'beo'],
            'notifications' => ['notification', 'notificacion', 'notificación'],
        ] as $module => $terms) {
            foreach ($terms as $term) {
                if (preg_match('/\b'.$term.'\b/iu', $message) === 1) {
                    $modules[] = $module;
                    break;
                }
            }
        }

        foreach ((array) ($context['active_entities'] ?? []) as $entity) {
            if (!is_array($entity)) {
                continue;
            }
            $type = (string) ($entity['type'] ?? '');
            $modules[] = match ($type) {
                'recipe' => 'recipes', 'menu', 'menu_item' => 'menus', 'prep_list', 'prep_item' => 'prep',
                'task' => 'tasks', 'event', 'client', 'contact', 'venue' => 'events',
                'team', 'station', 'shift', 'membership' => 'team_staff',
                'document', 'beo' => 'documents', default => null,
            };
        }

        $modules = array_values(array_unique(array_filter($modules)));
        if ($modules === []) {
            return ['profile' => 'all', 'metadata' => $metadata];
        }

        $dependencies = [
            'recipes' => ['recipes', 'menus', 'prep'],
            'menus' => ['menus', 'recipes', 'prep'],
            'prep' => ['prep', 'recipes', 'menus', 'events'],
            'tasks' => ['tasks', 'team_staff', 'events'],
            'events' => ['events', 'documents', 'menus', 'prep'],
            'team_staff' => ['team_staff', 'events'],
            'documents' => ['documents', 'events'],
            'notifications' => ['notifications', 'workspace'],
        ];
        $allowed = array_values(array_unique(array_merge(...array_map(
            fn (string $module): array => $dependencies[$module] ?? [$module],
            $modules
        ))));

        $selected = array_values(array_filter(
            $metadata,
            fn (array $tool): bool => in_array((string) ($tool['module'] ?? ''), $allowed, true)
                || (
                    in_array('tasks', $modules, true)
                    || in_array('team_staff', $modules, true)
                ) && ($tool['key'] ?? null) === 'members.list'
        ));

        if (count($selected) < 2) {
            return ['profile' => 'all', 'metadata' => $metadata];
        }

        return [
            'profile' => implode('+', $modules),
            'metadata' => $selected,
        ];
    }
}
