<?php

namespace App\AI\EntityResolution;

class EntityResolverRegistry
{
    /** @var array<string, EntityResolverAdapter> */
    private array $adapters = [];

    /** @param iterable<EntityResolverAdapter> $adapters */
    public function __construct(iterable $adapters = [])
    {
        foreach ($adapters as $adapter) {
            $this->register($adapter);
        }
    }

    public function register(EntityResolverAdapter $adapter): void
    {
        $this->adapters[$adapter->entityType()] = $adapter;
    }

    public function forType(string $entityType): ?EntityResolverAdapter
    {
        return $this->adapters[$entityType] ?? null;
    }
}
