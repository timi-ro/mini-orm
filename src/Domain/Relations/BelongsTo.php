<?php

declare(strict_types=1);

namespace MiniOrm\Domain\Relations;

use MiniOrm\Domain\Model;

/**
 * Post belongsTo User:
 *   $foreignKey = posts.user_id  (key on the current/child table)
 *   $localKey   = users.id       (key on the owner/related table)
 */
class BelongsTo extends Relation
{
    public function getResults(): ?Model
    {
        return ($this->relatedClass)::query()
            ->where($this->localKey, $this->parent->getAttribute($this->foreignKey))
            ->first();
    }
}