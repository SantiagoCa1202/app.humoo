<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\Menu\MenuDraftParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RuleBasedAIProvider implements AIProvider
{
    public function __construct(private ?MenuDraftParser $menuDraftParser = null)
    {
        $this->menuDraftParser ??= new MenuDraftParser();
    }

    public function generate(array $context): array
    {
        $message = trim((string) ($context['message'] ?? ''));
        $normalized = Str::lower($message);
        $locale = $this->normalizeLocale($context['locale'] ?? null);

        return [
            'model' => (string) config('ai.providers.rule_based.model', 'humoo-rule-based'),
            'provider' => 'rule_based',
            'usage' => [
                'completion_tokens' => 0,
                'prompt_tokens' => 0,
                'total_tokens' => 0,
            ],
            ...$this->resolveIntent($message, $normalized, $locale, $context),
        ];
    }

    private function resolveIntent(
        string $message,
        string $normalized,
        string $locale,
        array $context
    ): array {
        if (Str::startsWith($normalized, 'chat:')) {
            return $this->resolveStructuredCommand($normalized);
        }

        if ($this->looksLikeMenuCreation($normalized)) {
            return [
                'intent' => 'create_menu',
                'slots' => [
                    'menu_draft' => $this->menuDraftParser->parse($message),
                ],
            ];
        }

        if ($this->looksLikeTaskUpdate($normalized)) {
            return [
                'intent' => 'update_task',
                'slots' => [
                    'assignee_name' => $this->extractAssigneeName($message),
                    'ordinal' => $this->extractOrdinal($normalized),
                    'search' => $this->extractTaskSearch($message),
                    'status' => $this->extractTaskStatus($normalized),
                ],
            ];
        }

        if ($this->containsAny($normalized, ['abre el', 'open the', 'open second', 'abre segundo'])) {
            return [
                'intent' => 'show_event_summary',
                'slots' => [
                    'ordinal' => $this->extractOrdinal($normalized) ?? 1,
                ],
            ];
        }

        if (
            $this->containsAny($normalized, ['prep pendiente', 'pending prep'])
            || (
                $this->containsAny($normalized, ['pendiente', 'pending'])
                && $this->containsAny($normalized, ['evento', 'event'])
            )
        ) {
            return [
                'intent' => 'show_pending_for_event',
                'slots' => [
                    'event_search' => $this->extractEventSearch($message),
                    'time_filter' => $this->extractTimeFilter($normalized, $context),
                ],
            ];
        }

        if ($this->containsAny($normalized, ['prep', 'mise en place', 'produccion', 'producción'])) {
            return [
                'intent' => 'show_prep_for_event',
                'slots' => [
                    'event_search' => $this->extractEventSearch($message),
                    'time_filter' => $this->extractTimeFilter($normalized, $context),
                ],
            ];
        }

        if ($this->containsAny($normalized, ['tarea', 'tareas', 'task', 'tasks', 'pendiente'])) {
            return [
                'intent' => 'show_my_tasks',
                'slots' => [
                    'event_search' => $this->extractEventSearch($message),
                    'time_filter' => $this->extractTimeFilter($normalized, $context),
                ],
            ];
        }

        if ($this->containsAny($normalized, ['evento', 'event', 'events'])) {
            return [
                'intent' => 'show_events',
                'slots' => [
                    'event_search' => $this->extractEventSearch($message),
                    'time_filter' => $this->extractTimeFilter($normalized, $context),
                ],
            ];
        }

        return [
            'intent' => 'clarify_scope',
            'slots' => [
                'locale' => $locale,
            ],
        ];
    }

    private function resolveStructuredCommand(string $normalized): array
    {
        $parts = explode(':', $normalized);

        if (($parts[1] ?? null) !== 'event' || !isset($parts[2], $parts[3])) {
            return [
                'intent' => 'clarify_scope',
                'slots' => [],
            ];
        }

        return match ($parts[3]) {
            'pending' => [
                'intent' => 'show_pending_for_selected_event',
                'slots' => ['event_id' => $parts[2]],
            ],
            'prep' => [
                'intent' => 'show_prep_for_selected_event',
                'slots' => ['event_id' => $parts[2]],
            ],
            'summary' => [
                'intent' => 'show_selected_event_summary',
                'slots' => ['event_id' => $parts[2]],
            ],
            'tasks' => [
                'intent' => 'show_tasks_for_selected_event',
                'slots' => ['event_id' => $parts[2]],
            ],
            default => [
                'intent' => 'clarify_scope',
                'slots' => [],
            ],
        };
    }

    private function extractTimeFilter(string $normalized, array $context): array
    {
        $timezone = (string) ($context['timezone'] ?? 'UTC');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($this->containsAny($normalized, ['mañana', 'manana', 'tomorrow'])) {
            $target = $today->addDay();

            return [
                'date_from' => $target->toDateString(),
                'date_to' => $target->toDateString(),
                'label' => 'tomorrow',
            ];
        }

        if ($this->containsAny($normalized, ['hoy', 'today'])) {
            return [
                'date_from' => $today->toDateString(),
                'date_to' => $today->toDateString(),
                'label' => 'today',
            ];
        }

        return [];
    }

    private function extractEventSearch(string $message): ?string
    {
        $patterns = [
            '/(?:evento|event)\s+(?:de|named|llamado)\s+["“]?([^"”]+)["”]?/iu',
            '/(?:evento|event)\s+["“]?([^"”]+)["”]?/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $value = trim((string) ($matches[1] ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractTaskSearch(string $message): ?string
    {
        $patterns = [
            '/(?:tarea|task)\s+(?:de|named|llamada|llamado)\s+["“]?([^"”]+)["”]?/iu',
            '/["“]([^"”]+)["”]/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $value = trim((string) ($matches[1] ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractTaskStatus(string $normalized): ?string
    {
        if ($this->containsAny($normalized, ['done', 'hecha', 'hecho', 'completa', 'complete'])) {
            return 'done';
        }

        if ($this->containsAny($normalized, ['in progress', 'en progreso', 'start'])) {
            return 'in_progress';
        }

        if ($this->containsAny($normalized, ['blocked', 'bloquea', 'bloqueada'])) {
            return 'blocked';
        }

        if ($this->containsAny($normalized, ['cancel', 'cancela'])) {
            return 'cancelled';
        }

        return null;
    }

    private function extractAssigneeName(string $message): ?string
    {
        $patterns = [
            '/(?:assign|asigna(?:le)?|reasigna(?:le)?)\s+(?:to\s+|a\s+)([[:alpha:]][[:alpha:]\s\'-]{1,40})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $value = trim((string) ($matches[1] ?? ''));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractOrdinal(string $normalized): ?int
    {
        $map = [
            'primer' => 1,
            'primera' => 1,
            'primero' => 1,
            'first' => 1,
            'segundo' => 2,
            'segunda' => 2,
            'second' => 2,
            'tercer' => 3,
            'tercero' => 3,
            'tercera' => 3,
            'third' => 3,
            'cuarto' => 4,
            'cuarta' => 4,
            'fourth' => 4,
        ];

        foreach ($map as $needle => $value) {
            if (Str::contains($normalized, $needle)) {
                return $value;
            }
        }

        if (preg_match('/\b(\d+)(?:ro|do|to|th)?\b/u', $normalized, $matches) === 1) {
            $value = (int) ($matches[1] ?? 0);

            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function looksLikeTaskUpdate(string $normalized): bool
    {
        return $this->containsAny($normalized, ['asigna', 'assign', 'marca', 'mark', 'completa', 'complete', 'pone', 'pon', 'set'])
            && $this->containsAny($normalized, ['tarea', 'task']);
    }

    private function looksLikeMenuCreation(string $normalized): bool
    {
        return $this->containsAny($normalized, ['crea un menu', 'crea un menú', 'crear menu', 'crear menú', 'create a menu', 'create menu'])
            && $this->containsAny($normalized, ['menu', 'menú']);
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        return collect($needles)->contains(
            fn (string $needle) => Str::contains($haystack, Str::lower($needle))
        );
    }

    private function normalizeLocale(mixed $value): string
    {
        $locale = Str::lower(substr((string) $value, 0, 2));

        return in_array($locale, ['en', 'es'], true) ? $locale : 'en';
    }
}
