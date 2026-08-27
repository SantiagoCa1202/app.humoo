<?php

namespace App\AI\Orchestration;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

final class MessageLocaleResolver
{
    public function resolve(?string $requestedLocale, string $message, Workspace $workspace, User $user): string
    {
        $explicit = $this->normalize($requestedLocale);
        if ($explicit !== null && $message === '') {
            return $explicit;
        }

        $detected = $this->detect($message);
        if ($detected !== null) {
            return $detected;
        }

        return $explicit
            ?? $this->normalize($user->locale ?? null)
            ?? $this->normalize($workspace->default_locale ?? null)
            ?? $this->normalize(config('app.locale'))
            ?? 'en';
    }

    private function detect(string $message): ?string
    {
        $normalized = Str::lower(Str::ascii($message));
        if (trim($normalized) === '') {
            return null;
        }

        $spanish = $this->score($normalized, ['crea', 'siguiente', 'ingredientes', 'aderezo', 'preparacion', 'corta', 'mezcla', 'rocia', 'agrega', 'porciones']);
        $english = $this->score($normalized, ['create', 'ingredients', 'preparation', 'method', 'steps', 'servings', 'mix', 'slice']);

        if ($spanish >= 2 && $spanish > $english) {
            return 'es';
        }
        if ($english >= 2 && $english > $spanish) {
            return 'en';
        }

        return null;
    }

    /** @param array<int, string> $terms */
    private function score(string $message, array $terms): int
    {
        return collect($terms)->sum(fn (string $term): int => preg_match('/\b'.preg_quote($term, '/').'\b/u', $message) === 1 ? 1 : 0);
    }

    private function normalize(mixed $locale): ?string
    {
        $locale = strtolower(substr(trim((string) $locale), 0, 2));

        return in_array($locale, ['en', 'es'], true) ? $locale : null;
    }
}
