<?php

namespace App\AI\Orchestration;

use App\Models\User;
use App\Models\Workspace;
final class MessageLocaleResolver
{
    public function resolve(?string $requestedLocale, string $message, Workspace $workspace, User $user): string
    {
        return $this->normalize($requestedLocale)
            ?? $this->normalize($user->locale ?? null)
            ?? $this->normalize($workspace->default_locale ?? null)
            ?? $this->normalize(config('app.locale'))
            ?? 'en';
    }

    private function normalize(mixed $locale): ?string
    {
        $locale = strtolower(substr(trim((string) $locale), 0, 2));

        return in_array($locale, ['en', 'es'], true) ? $locale : null;
    }
}
