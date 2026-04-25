<?php

declare(strict_types=1);

namespace MiniOrm\Domain\Relations;

use MiniOrm\Domain\Model;

abstract class Relation
{
    public function __construct(
        protected readonly Model $parent,
        protected readonly string $relatedClass,
        protected readonly string $foreignKey,
        protected readonly string $localKey
    ) {}

    abstract public function getResults(): mixed;
}