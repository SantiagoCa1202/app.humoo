<?php

namespace App\AI\EntityResolution;

use Illuminate\Support\Str;

class EntityReferenceNormalizer
{
    /** @return string[] */
    public function variants(?string $reference): array
    {
        $original = $this->normalize($reference);
        if ($original === '') {
            return [];
        }

        // Keep the complete reference first: titles beginning with an article remain exact-matchable.
        $withoutLeadingHelper = preg_replace('/^(?:the|a|an|el|la|los|las|del|de)\s+/iu', '', $original);

        return array_values(array_unique(array_filter([
            $original,
            trim((string) $withoutLeadingHelper),
            rtrim($original, 's'),
            rtrim(trim((string) $withoutLeadingHelper), 's'),
        ])));
    }

    public function normalize(?string $value): string
    {
        $ascii = Str::lower(Str::ascii(trim((string) $value)));
        $spaced = preg_replace('/([a-z])([0-9])|([0-9])([a-z])/i', '$1$3 $2$4', $ascii);
        $clean = preg_replace('/[^a-z0-9]+/', ' ', (string) $spaced);

        return trim((string) preg_replace('/\s+/', ' ', (string) $clean));
    }

    /** @return string[] */
    public function tokens(string $value): array
    {
        return array_values(array_filter(explode(' ', $this->normalize($value)), fn (string $token) => strlen($token) > 1));
    }
}
