<?php

declare(strict_types=1);

namespace MiniOrm\Domain\Relations;

use MiniOrm\Domain\Model;

/**
 * User hasOne Profile:
 *   $foreignKey = profiles.user_id  (key on the related/child table)
 *   $localKey   = users.id          (key on the parent table)
 */
class HasOne extends Relation
{
    public function getResults(): ?Model
    {
        return ($this->relatedClass)::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->first();
    }
}