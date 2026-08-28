<?php

namespace App\AI\Capabilities;

use App\AI\Tools\ToolRegistry;

/**
 * Versioned contract boundary for all operational chat capabilities.
 *
 * ToolRegistry remains the backwards-compatible service name while this
 * registry exposes the canonical contract consumed by new integrations.
 */
class CapabilityRegistry extends ToolRegistry
{
    public const REGISTRY_VERSION = 'capabilities-v1';

    public function registryVersion(): string
    {
        return self::REGISTRY_VERSION;
    }

    public function registryHash(): string
    {
        $definitions = collect(parent::allMetadata())
            ->sortBy('action_key')
            ->map(static function (array $definition): array {
                unset($definition['legacy_action_aliases']);

                return $definition;
            })
            ->values()
            ->all();

        return hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function definition(string $actionId): array
    {
        $tool = $this->resolve($actionId);
        $definition = $this->metadata($tool);

        return [
            ...$definition,
            'registry_hash' => $this->registryHash(),
            'registry_version' => $this->registryVersion(),
        ];
    }

    public function definitions(): array
    {
        return array_map(fn (array $definition): array => [
            ...$definition,
            'registry_hash' => $this->registryHash(),
            'registry_version' => $this->registryVersion(),
        ], parent::allMetadata());
    }

    /** @return array<string, mixed> */
    public function functionDefinition(string $actionId): array
    {
        return (new OpenAiFunctionSchemaFactory())->make($this->definition($actionId));
    }

    /** @param array<int, string>|null $actionKeys @return array<int, array<string, mixed>> */
    public function functionDefinitions(?array $actionKeys = null): array
    {
        $definitions = $actionKeys === null
            ? $this->definitions()
            : array_map(fn (string $actionKey): array => $this->definition($actionKey), $actionKeys);

        $factory = new OpenAiFunctionSchemaFactory();

        return array_map(fn (array $definition): array => $factory->make($definition), $definitions);
    }
}
