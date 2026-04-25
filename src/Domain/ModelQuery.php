<?php

declare(strict_types=1);

namespace MiniOrm\Domain;

use MiniOrm\Core\QueryBuilder;

/**
 * Chainable query scope that hydrates results into model instances.
 * Returned by Model::query() and Model::where().
 */
class ModelQuery
{
    public function __construct(
        private QueryBuilder $qb,
        private string $modelClass
    ) {}

    public function select(string|array $columns): static
    {
        $this->qb->select($columns);
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $this->qb->where($column, $operatorOrValue);
        } else {
            $this->qb->where($column, $operatorOrValue, $value);
        }
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->qb->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->qb->whereNotNull($column);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->qb->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->qb->limit($limit);
        return $this;
    }

    /** @return list<Model> */
    public function get(): array
    {
        return array_map(
            fn($row) => ($this->modelClass)::fromRow($row),
            $this->qb->get()
        );
    }

    public function first(): ?Model
    {
        $row = $this->qb->first();
        return $row !== null ? ($this->modelClass)::fromRow($row) : null;
    }

    public function count(): int
    {
        return $this->qb->count();
    }

    public function exists(): bool
    {
        return $this->qb->exists();
    }
}