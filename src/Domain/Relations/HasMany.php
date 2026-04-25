<?php

declare(strict_types=1);

namespace MiniOrm\Domain\Relations;

use MiniOrm\Domain\Model;

/**
 * User hasMany Posts:
 *   $foreignKey = posts.user_id  (key on the related/child table)
 *   $localKey   = users.id       (key on the parent table)
 */
class HasMany extends Relation
{
    /** @return list<Model> */
    public function getResults(): array
    {
        return ($this->relatedClass)::query()
            ->where($this->foreignKey, $this->parent->getAttribute($this->localKey))
            ->get();
    }
}