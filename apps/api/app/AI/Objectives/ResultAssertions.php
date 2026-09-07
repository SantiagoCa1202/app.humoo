<?php

namespace App\AI\Objectives;

use Illuminate\Validation\ValidationException;

/** Structural checks over registered tool results, independent of any module. */
final class ResultAssertions
{
    public static function validate(array $assertions): array
    {
        validator(['assertions' => $assertions], [
            'assertions' => ['array', 'max:200'],
            'assertions.*.path' => ['present', 'array', 'max:20'],
            'assertions.*.path.*' => ['required'],
            'assertions.*.operator' => ['required', 'in:exists,equals,count_equals,contains'],
            'assertions.*.value' => ['present'],
        ])->validate();
        foreach ($assertions as $assertion) {
            foreach ($assertion['path'] as $segment) {
                if (! is_string($segment) && ! is_int($segment)) {
                    throw ValidationException::withMessages(['assertions.path' => ['Use structured string or integer path segments.']]);
                }
            }
        }

        return $assertions;
    }

    public static function check(array $result, array $assertions, array $evidence): void
    {
        foreach (self::validate($assertions) as $index => $assertion) {
            $missing = new \stdClass;
            $actual = $assertion['path'] === [] ? $result : data_get($result, $assertion['path'], $missing);
            $expected = $assertion['value'];
            if (is_array($expected) && isset($expected['$from'])) {
                $path = explode('.', (string) $expected['$from']);
                $source = array_shift($path);
                $expected = data_get($evidence[$source] ?? [], $path, $missing);
            }
            $valid = $actual !== $missing && $expected !== $missing && match ($assertion['operator']) {
                'exists' => $actual !== null && $actual !== [] && $actual !== '',
                'equals' => $actual === $expected,
                'count_equals' => is_array($actual) && is_int($expected) && count($actual) === $expected,
                'contains' => is_array($actual) && in_array($expected, $actual, true),
            };
            if (! $valid) {
                throw ValidationException::withMessages(['assertions.'.$index => ['RESULT_ASSERTION_FAILED']]);
            }
        }
    }
}
