<?php

declare(strict_types=1);

namespace MiniOrm\Domain;

/**
 * Centralizes the strategy for mapping raw database rows to model instances.
 * All hydration goes through here — no __set magic, no property reflection.
 */
class Hydrator
{
    public static function hydrate(string $modelClass, array $row): Model
    {
        return $modelClass::fromRow($row);
    }

    /** @return list<Model> */
    public static function hydrateAll(string $modelClass, array $rows): array
    {
        return array_map(
            fn($row) => static::hydrate($modelClass, $row),
            $rows
        );
    }
}