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

        if ($this->isGeneralQuestion($normalized)) {
            return [
                'intent' => 'clarify_scope',
                'slots' => [
                    'locale' => $locale,
                ],
            ];
        }

        if ($this->looksLikeMenuCreation($normalized)) {
            return [
                'intent' => 'create_menu',
                'slots' => [
                    'menu_draft' => $this->menuDraftParser->parse($message),
                ],
            ];
        }

        $menuRecipeIntent = $this->resolveMenuRecipeIntent($message, $normalized);
        if ($menuRecipeIntent !== null) {
            return $menuRecipeIntent;
        }

        if ($this->looksLikeTaskCreation($normalized)) {
            return [
                'intent' => 'create_task',
                'slots' => [
                    'task_title' => $this->extractTaskTitle($message),
                    ...$this->extractTaskSchedule($normalized, $context),
                    'task_priority' => $this->extractTaskPriority($normalized),
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

        $directoryIntent = $this->resolveDirectoryIntent($message, $normalized, $context);
        if ($directoryIntent !== null) {
            return $directoryIntent;
        }

        $unsupportedCapability = $this->resolveUnsupportedCapability($message, $normalized, $locale);

        if ($unsupportedCapability !== null) {
            return $unsupportedCapability;
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

    private function resolveDirectoryIntent(string $message, string $normalized, array $context): ?array
    {
        $type = $this->firstMatchingTerm($normalized, [
            'event' => ['event', 'evento', 'events', 'eventos'],
            'client' => ['client', 'cliente', 'clients', 'clientes'],
            'contact' => ['contact', 'contacto', 'contacts', 'contactos'],
            'venue' => ['venue', 'venues', 'lugar', 'lugares', 'location', 'locations'],
        ]);

        if ($type === null || $this->containsAny($normalized, [
            'como funciona', 'como se hace', 'how does', 'how do i', 'que es', 'what is',
        ])) {
            return null;
        }

        $operation = $this->firstMatchingTerm($normalized, [
            'delete' => ['delete', 'elimina', 'eliminar', 'remove', 'remover', 'borra', 'borrar'],
            'cancel' => ['cancel', 'cancela', 'cancelar'],
            'update' => ['update', 'actualiza', 'actualizar', 'change', 'cambia', 'cambiar', 'modify', 'modifica', 'modificar', 'move', 'mueve', 'set', 'pon'],
            'create' => ['create', 'crea', 'crear', 'new', 'nuevo', 'nueva', 'add', 'agrega', 'agregar'],
            'list' => ['list', 'lista', 'listar', 'show', 'muestra', 'mostrar', 'ver', 'view', 'see', 'ensena', 'enseña'],
        ]);

        if ($operation === null) {
            return null;
        }

        if ($type !== 'event' && $operation === 'cancel') {
            return null;
        }

        $search = $this->extractDirectorySearch($message, $type, $operation);
        if ($operation === 'list' && $search !== null) {
            $operation = 'detail';
        }

        $action = match (true) {
            $operation === 'list' => $type.'.list',
            $operation === 'detail' => $type.'.detail',
            $operation === 'cancel' => 'events.cancel',
            default => $type.'.'.$operation,
        };

        if ($action === 'events.list') {
            return [
                'intent' => 'show_events',
                'slots' => [
                    'event_search' => $search,
                    'time_filter' => $this->extractTimeFilter($normalized, $context),
                ],
            ];
        }

        $input = $this->extractDirectoryInput($message, $normalized, $type, $operation, $context);

        return [
            'intent' => 'tool_action',
            'slots' => [
                'action_key' => $action,
                'entity_type' => $type,
                'entity_search' => $search,
                'input' => $input,
            ],
        ];
    }

    private function extractDirectorySearch(string $message, string $type, string $operation): ?string
    {
        $quoted = preg_match('/["“]([^"”]+)["”]/u', $message, $matches) === 1
            ? trim((string) ($matches[1] ?? ''))
            : null;
        if ($quoted !== null && $quoted !== '') {
            return $quoted;
        }

        $patterns = [
            '/(?:called|named|llamado|llamada|de nombre|de)\s+(.+?)(?=\s+(?:for|with|at|on|tomorrow|today|para|con|a las|el|la)\b|$)/iu',
            '/(?:'.$this->directoryNounPattern($type).')\s+(?:of|del)\s+(.+?)(?=\s+(?:with|at|on|to|and|y|con|a las)\b|$)/iu',
            '/(?:'.$this->directoryNounPattern($type).')\s+(.+?)(?=\s+(?:with|at|on|tomorrow|today|con|a las|to|and|y|guest|guests|personas)\b|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $value = trim((string) ($matches[1] ?? ''));
                $value = preg_replace('/\s+(?:client|cliente|contact|contacto|venue|lugar|event|evento)$/iu', '', $value) ?? $value;
                if ($value !== '' && !preg_match('/^(?:a|an|the|el|la|me|my|mis|all|todos|todas)$/iu', $value)) {
                    return substr($value, 0, 180);
                }
            }
        }

        return null;
    }

    private function directoryNounPattern(string $type): string
    {
        return match ($type) {
            'event' => 'events?|eventos?',
            'client' => 'clients?|clientes?',
            'contact' => 'contacts?|contactos?',
            default => 'venues?|lugares?|locations?',
        };
    }

    private function extractDirectoryInput(string $message, string $normalized, string $type, string $operation, array $context): array
    {
        $input = [];
        $name = $this->extractDirectoryName($message, $type);

        if ($operation === 'create' && $name !== null) {
            if ($type === 'contact') {
                [$firstName, $lastName] = $this->splitPersonName($name);
                $input['first_name'] = $firstName;
                $input['last_name'] = $lastName;
            } else {
                $input['name'] = $name;
            }
        }

        if ($type === 'contact') {
            $clientSearch = $this->extractRelatedSearch($message, ['client', 'cliente', 'company', 'empresa']);
            if ($clientSearch !== null) {
                $input['client_search'] = $clientSearch;
            }
        }

        if ($type === 'event') {
            $time = $this->extractDirectoryDateTime($normalized, $context);
            if ($time !== null) {
                $input['starts_at'] = $time;
            }

            if (preg_match('/\b(\d+)\s*(?:guests?|personas?|people)\b/iu', $message, $matches) === 1) {
                $input['guest_count_expected'] = (int) $matches[1];
            }

            if (preg_match('/(?:guest\s+count|cantidad\s+de\s+invitados)\s*(?:is|to|a|:)\s*(\d+)/iu', $message, $matches) === 1) {
                $input['guest_count_expected'] = (int) $matches[1];
            }

            $venueSearch = $this->extractRelatedSearch($message, ['venue', 'lugar', 'location']);
            if ($venueSearch !== null) {
                $input['venue_search'] = $venueSearch;
            }
        }

        foreach ([
            'phone' => ['phone', 'teléfono', 'telefono'],
            'email' => ['email', 'correo'],
            'city' => ['city', 'ciudad'],
        ] as $field => $needles) {
            foreach ($needles as $needle) {
                $pattern = '/'.preg_quote($needle, '/').'\s*(?:is|es|to|a|:)\s*([^,.;\n]+)/iu';
                if (preg_match($pattern, $message, $matches) === 1) {
                    $input[$field] = trim((string) ($matches[1] ?? ''));
                    break;
                }
            }
        }

        if ($type === 'contact' && $this->containsAny($normalized, ['primary', 'principal'])) {
            $input['is_primary'] = true;
        }

        return $input;
    }

    private function extractDirectoryName(string $message, string $type): ?string
    {
        $quoted = preg_match('/["“]([^"”]+)["”]/u', $message, $matches) === 1
            ? trim((string) ($matches[1] ?? ''))
            : null;
        if ($quoted !== null && $quoted !== '') {
            return substr($quoted, 0, 180);
        }

        if (preg_match('/(?:called|named|llamado|llamada|de nombre)\s+(.+?)(?=\s+(?:for|with|at|on|tomorrow|today|para|con|a las)\b|$)/iu', $message, $matches) === 1) {
            return substr(trim((string) ($matches[1] ?? '')), 0, 180);
        }

        if ($type !== 'event' && preg_match('/(?:'.$this->directoryNounPattern($type).')\s+(.+?)(?=\s+(?:for|of|with|para|de|con)\b|$)/iu', $message, $matches) === 1) {
            $value = trim((string) ($matches[1] ?? ''));
            return $value !== '' ? substr($value, 0, 180) : null;
        }

        return null;
    }

    private function splitPersonName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];
        return [(string) ($parts[0] ?? $name), $parts[1] ?? null];
    }

    private function extractRelatedSearch(string $message, array $needles): ?string
    {
        $pattern = '/(?:'.implode('|', array_map(fn (string $needle): string => preg_quote($needle, '/'), $needles)).')\s*(?:is|es|named|llamado|de)?\s+(.+?)(?=\s+(?:as|como|with|con|and|y)\b|$)/iu';
        if (preg_match($pattern, $message, $matches) === 1) {
            $value = trim((string) ($matches[1] ?? ''));
            return $value !== '' ? substr($value, 0, 180) : null;
        }
        return null;
    }

    private function extractDirectoryDateTime(string $normalized, array $context): ?string
    {
        $timezone = (string) ($context['timezone'] ?? 'UTC');
        $date = CarbonImmutable::now($timezone)->startOfDay();
        if ($this->containsAny($normalized, ['tomorrow', 'mañana', 'manana'])) {
            $date = $date->addDay();
        } elseif (!$this->containsAny($normalized, ['today', 'hoy'])) {
            return null;
        }

        if (preg_match('/\b(?:at|a las?)\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/iu', $normalized, $matches) !== 1) {
            return $date->setTime(9, 0)->toIso8601String();
        }

        $hour = (int) $matches[1];
        $minute = (int) ($matches[2] ?? 0);
        $period = Str::lower((string) ($matches[3] ?? ''));
        if ($period === 'pm' && $hour < 12) $hour += 12;
        if ($period === 'am' && $hour === 12) $hour = 0;
        return $date->setTime($hour, $minute)->toIso8601String();
    }

    private function resolveUnsupportedCapability(
        string $message,
        string $normalized,
        string $locale
    ): ?array {
        if ($this->containsAny($normalized, [
            'como funciona',
            'como se hace',
            'how does',
            'how do i',
            'que es',
            'what is',
            'puede humoo',
            'can humoo',
        ])) {
            return null;
        }

        $verb = $this->firstMatchingTerm($normalized, [
            'send' => ['send', 'enviar', 'envia', 'envÃ­a', 'manda', 'mandar'],
            'export' => ['export', 'exporta', 'exportar'],
            'generate' => ['generate', 'genera', 'generar'],
            'create' => ['create', 'crea', 'crear'],
            'schedule' => ['schedule', 'programa', 'programar'],
            'sync' => ['sync', 'sincroniza', 'sincronizar'],
            'notify' => ['notify', 'notifica', 'notificar'],
        ]);

        if ($verb === null) {
            return null;
        }

        if (
            $this->containsAny($normalized, ['prep', 'mise en place', 'preparacion', 'preparaciÃ³n'])
            && $this->containsAny($normalized, ['supplier', 'proveedor'])
            && $verb === 'send'
        ) {
            return [
                'intent' => 'unsupported_capability',
                'slots' => [
                    'confidence' => 0.95,
                    'detected_intent' => 'send_prep_to_supplier',
                    'module' => 'purchasing',
                    'normalized_key' => 'purchasing.send_prep_to_supplier',
                    'requested_action' => $locale === 'es'
                        ? 'enviar lista de preparacion al proveedor'
                        : 'send prep list to supplier',
                ],
            ];
        }

        $object = $this->firstMatchingTerm($normalized, [
            'report' => ['report', 'reporte', 'informe'],
            'invoice' => ['invoice', 'factura'],
            'supplier' => ['supplier', 'proveedor'],
            'inventory' => ['inventory', 'inventario'],
            'client' => ['client', 'cliente'],
            'event' => ['event', 'evento'],
        ]);

        if ($object === null) {
            return null;
        }

        $module = match ($object) {
            'invoice', 'supplier' => 'purchasing',
            'report' => 'reporting',
            'inventory' => 'inventory',
            'client' => 'directory',
            default => 'operations',
        };
        $detectedIntent = $verb.'_'.$object;

        return [
            'intent' => 'unsupported_capability',
            'slots' => [
                'confidence' => 0.8,
                'detected_intent' => $detectedIntent,
                'module' => $module,
                'normalized_key' => $module.'.'.$detectedIntent,
                'requested_action' => Str::lower(trim($message)),
            ],
        ];
    }

    private function isGeneralQuestion(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'como funciona',
            'como se hace',
            'how does',
            'how do i',
            'que es',
            'what is',
        ]);
    }

    private function firstMatchingTerm(string $haystack, array $terms): ?string
    {
        foreach ($terms as $value => $needles) {
            if ($this->containsAny($haystack, $needles)) {
                return $value;
            }
        }

        return null;
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

    private function looksLikeTaskCreation(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'create task',
            'create a task',
            'create one task',
            'crea tarea',
            'crea una tarea',
            'crea task',
            'crea una task',
            'crear tarea',
            'crear una tarea',
            'crear task',
            'agrega tarea',
            'agregar tarea',
            'nueva tarea',
            'new task',
            'add task',
        ]);
    }

    private function extractTaskTitle(string $message): ?string
    {
        $patterns = [
            '/(?:tarea|task)\s+(?:llamada|llamado|named|called|titled|de nombre)\s+["“]?(.+?)["”]?(?=\s+(?:para|on|at|tomorrow|today|mañana|manana|hoy)\b|$)/iu',
            '/(?:create|crea|crear|add|agrega)\s+(?:a\s+|una?\s+)?(?:task|tarea)\s*[:\-]\s*["“]?(.+?)["”]?$/iu',
            '/(?:task|tarea)\s+["“]([^"”]+)["”]/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches) === 1) {
                $title = trim((string) ($matches[1] ?? ''));

                if ($title !== '') {
                    return substr($title, 0, 255);
                }
            }
        }

        return null;
    }

    private function extractTaskSchedule(string $normalized, array $context): array
    {
        $timezone = (string) ($context['timezone'] ?? 'UTC');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $date = null;

        if ($this->containsAny($normalized, ['mañana', 'manana', 'tomorrow'])) {
            $date = $today->addDay();
        } elseif ($this->containsAny($normalized, ['hoy', 'today'])) {
            $date = $today;
        }

        if ($date === null || preg_match('/(?:a las|at)\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/iu', $normalized, $matches) !== 1) {
            return [];
        }

        $hour = (int) ($matches[1] ?? 0);
        $minute = (int) ($matches[2] ?? 0);
        $meridiem = Str::lower((string) ($matches[3] ?? ''));
        if ($meridiem === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($meridiem === 'am' && $hour === 12) {
            $hour = 0;
        }

        if ($hour > 23 || $minute > 59) {
            return [];
        }

        $startsAt = $date->setTime($hour, $minute);
        $schedule = ['starts_at' => $startsAt->toIso8601String()];

        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:hours?|hrs?|horas?|h)\b/iu', $normalized, $durationMatches) === 1) {
            $durationMinutes = (int) round((float) $durationMatches[1] * 60);
            if ($durationMinutes > 0) {
                $schedule['due_at'] = $startsAt->addMinutes($durationMinutes)->toIso8601String();
            }
        }

        return $schedule;
    }

    private function extractTaskPriority(string $normalized): ?string
    {
        foreach ([
            'urgent' => ['urgent', 'urgente'],
            'high' => ['high priority', 'alta prioridad', 'alta'],
            'low' => ['low priority', 'baja prioridad', 'baja'],
        ] as $priority => $terms) {
            if ($this->containsAny($normalized, $terms)) {
                return $priority;
            }
        }

        return null;
    }

    private function looksLikeMenuCreation(string $normalized): bool
    {
        return $this->containsAny($normalized, [
            'crea un menu',
            'crea un menú',
            'crea este menu',
            'crea este menú',
            'crea el menu',
            'crea el menú',
            'crear menu',
            'crear menú',
            'create a menu',
            'create this menu',
            'create menu',
        ])
            && $this->containsAny($normalized, ['menu', 'menú']);
    }

    private function resolveMenuRecipeIntent(string $message, string $normalized): ?array
    {
        $hasMenu = $this->containsAny($normalized, ['menu', 'menú', 'menÃº']);
        $hasRecipe = $this->containsAny($normalized, ['recipe', 'recipes', 'receta', 'recetas']);
        $implicitMenuMove = $this->containsAny($normalized, ['move', 'mueve', 'mover'])
            && $this->containsAny($normalized, ['section', 'seccion', 'sección', 'hot food', 'cold food']);
        if (!$hasMenu && !$hasRecipe && !$implicitMenuMove) {
            return null;
        }

        $verb = $this->firstMatchingTerm($normalized, [
            'delete' => ['delete', 'elimina', 'eliminar', 'remove', 'borra', 'borrar'],
            'move' => ['move', 'mueve', 'mover'],
            'update' => ['update', 'actualiza', 'actualizar', 'change', 'cambia', 'modify', 'modifica', 'edita', 'editar'],
            'rename' => ['rename', 'renombra', 'renombrar', 'cambiar nombre'],
            'create' => ['create', 'crea', 'crear', 'new', 'nuevo', 'nueva', 'add', 'agrega'],
            'scale' => ['scale', 'escalar', 'escala', 'multiply', 'multiplica'],
            'versions' => ['version', 'versions', 'versiones', 'historial', 'history'],
            'show' => ['show', 'muestra', 'mostrar', 'muéstrame', 'muestrame', 'view', 'ver', 'display', 'lista', 'list', 'buscar', 'search'],
        ]);

        if ($hasRecipe) {
            $search = $this->extractQuotedOrAfter($message, ['recipe', 'receta']);
            if ($verb === 'create') {
                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.create', 'entity_type' => 'recipe', 'entity_search' => null, 'input' => ['recipe_draft' => ['name' => $search]]]];
            }
            if ($verb === 'scale') {
                preg_match('/\b(\d+(?:\.\d+)?)\s*(?:servings?|porciones?|people|personas?)\b/iu', $message, $matches);
                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.scale', 'entity_type' => 'recipe', 'entity_search' => $search, 'input' => ['recipe_search' => $search, 'target_quantity' => isset($matches[1]) ? (float) $matches[1] : null]]];
            }
            if ($verb === 'versions') {
                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.versions', 'entity_type' => 'recipe', 'entity_search' => $search, 'input' => ['recipe_search' => $search]]];
            }
            if ($verb === 'update') {
                return ['intent' => 'tool_action', 'slots' => ['action_key' => 'recipes.update', 'entity_type' => 'recipe', 'entity_search' => $search, 'input' => ['recipe_search' => $search]]];
            }
            if (in_array($verb, ['show', null], true)) {
                $action = $verb === 'show' && $this->containsAny($normalized, ['list', 'lista', 'all', 'todas']) ? 'recipes.list' : 'recipes.detail';
                return ['intent' => 'tool_action', 'slots' => ['action_key' => $action, 'entity_type' => 'recipe', 'entity_search' => $search, 'input' => ['recipe_search' => $search]]];
            }
            return null;
        }

        if ($verb === 'show' || $verb === 'versions') {
            $search = $this->extractQuotedOrAfter($message, ['menu', 'menú', 'menÃº']);
            if ($verb === 'versions') {
                return null;
            }
            return ['intent' => 'show_menu', 'slots' => ['menu_search' => $search, 'menu_id' => null]];
        }

        if ($verb === 'rename') {
            $menu = $this->extractMenuAfter($message);
            $name = $this->extractAfter($message, ['to', 'a', 'como']);
            return ['intent' => 'rename_menu', 'slots' => ['menu_search' => $menu, 'menu_id' => null, 'menu_name' => $name]];
        }

        if ($verb === 'move') {
            $item = $this->extractBetween($message, ['move', 'mueve', 'mover'], ['to', 'a']);
            $target = $this->extractBetween($message, ['to', 'a'], ['in', 'en']);
            $menu = $this->extractAfter($message, ['in', 'en']);
            return ['intent' => 'move_menu_item_section', 'slots' => [
                'menu_id' => null, 'menu_search' => $menu, 'menu_item_id' => null, 'menu_item_search' => $item,
                'target_section_id' => null, 'target_section_search' => $target,
            ]];
        }

        if ($verb === 'delete') {
            $item = $this->extractBetween($message, ['delete', 'elimina', 'remove', 'borra'], ['from', 'de', 'in', 'en']);
            $menu = $this->extractAfter($message, ['from', 'de', 'in', 'en']);
            return ['intent' => 'tool_action', 'slots' => ['action_key' => 'menus.items.delete', 'entity_type' => 'menu_item', 'entity_search' => $item, 'input' => ['item_search' => $item, 'menu_search' => $menu]]];
        }

        if ($verb === 'update') {
            return ['intent' => 'tool_action', 'slots' => ['action_key' => 'menus.items.update', 'entity_type' => 'menu_item', 'entity_search' => null, 'input' => ['menu_search' => $this->extractAfter($message, ['in', 'en']), 'item_search' => $this->extractBetween($message, ['update', 'change', 'cambia'], ['in', 'en'])]]];
        }

        return null;
    }

    private function extractQuotedOrAfter(string $message, array $nouns): ?string
    {
        if (preg_match('/["“]([^"”]+)["”]/u', $message, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }
        $pattern = '/(?:'.implode('|', array_map(fn (string $noun): string => preg_quote($noun, '/'), $nouns)).')\s+(.+?)(?=\s+(?:for|with|at|to|on|para|con|a|en)\b|$)/iu';
        return preg_match($pattern, $message, $matches) === 1 ? trim((string) ($matches[1] ?? '')) : null;
    }

    private function extractMenuAfter(string $message): ?string
    {
        return $this->extractAfter($message, ['menu', 'menú', 'menÃº']);
    }

    private function extractAfter(string $message, array $needles): ?string
    {
        $pattern = '/(?:'.implode('|', array_map(fn (string $needle): string => preg_quote($needle, '/'), $needles)).')\s+(.+)$/iu';
        return preg_match($pattern, $message, $matches) === 1 ? trim((string) ($matches[1] ?? '')) : null;
    }

    private function extractBetween(string $message, array $starts, array $ends): ?string
    {
        $pattern = '/(?:'.implode('|', array_map(fn (string $start): string => preg_quote($start, '/'), $starts)).')\s+(.+?)(?=\s+(?:'.implode('|', array_map(fn (string $end): string => preg_quote($end, '/'), $ends)).')\b|$)/iu';
        return preg_match($pattern, $message, $matches) === 1 ? trim((string) ($matches[1] ?? '')) : null;
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
