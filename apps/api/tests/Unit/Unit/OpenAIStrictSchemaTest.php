<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\OpenAIProvider;
use ReflectionMethod;
use Tests\TestCase;

class OpenAIStrictSchemaTest extends TestCase
{
    public function test_decision_schema_is_strict_at_every_object_level(): void
    {
        $method = new ReflectionMethod(OpenAIProvider::class, 'decisionSchema');
        $schema = $method->invoke(new OpenAIProvider);

        $this->assertStrictSchema($schema, '$');
    }

    private function assertStrictSchema(array $schema, string $path): void
    {
        $types = (array) ($schema['type'] ?? []);
        if (in_array('object', $types, true)) {
            $properties = $schema['properties'] ?? null;
            $this->assertIsArray($properties, "{$path} must define properties.");
            $this->assertFalse($schema['additionalProperties'] ?? true, "{$path} must disallow additional properties.");
            $this->assertSameCanonicalizing(array_keys($properties), $schema['required'] ?? [], "{$path} required must include every property.");

            foreach ($properties as $name => $property) {
                $this->assertIsArray($property, "{$path}.{$name} must be a schema object.");
                $this->assertStrictSchema($property, "{$path}.{$name}");
            }
        }

        if (in_array('array', $types, true)) {
            $this->assertArrayHasKey('items', $schema, "{$path} arrays must define items.");
            $this->assertIsArray($schema['items'], "{$path}.items must be a schema object.");
            $this->assertStrictSchema($schema['items'], "{$path}[]");
        }
    }
}
