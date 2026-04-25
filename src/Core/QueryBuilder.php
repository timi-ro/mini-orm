<?php

declare(strict_types=1);

namespace MiniOrm\Core;

use MiniOrm\Infrastructure\Connection;
use InvalidArgumentException;
use PDOStatement;

class QueryBuilder
{
    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderByColumn = null;
    private string $orderByDirection = 'ASC';
    private ?int $limitValue = null;

    private static array $allowedOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(private readonly Connection $connection) {}

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select(string|array $columns): static
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): static
    {
        if ($value === null) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = strtoupper((string) $operatorOrValue);
            if (!in_array($operator, self::$allowedOperators, true)) {
                throw new InvalidArgumentException("Unsupported operator: '{$operator}'");
            }
        }

        $this->wheres[] = "{$column} {$operator} ?";
        $this->bindings[] = $value;

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $upper = strtoupper($direction);
        if ($upper !== 'ASC' && $upper !== 'DESC') {
            throw new InvalidArgumentException("Order direction must be ASC or DESC, got '{$direction}'");
        }

        $this->orderByColumn = $column;
        $this->orderByDirection = $upper;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function get(): array
    {
        return $this->execute($this->buildSelectSql(), $this->bindings)->fetchAll();
    }

    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limitValue = 1;

        $result = $this->execute($clone->buildSelectSql(), $this->bindings)->fetch();
        return $result !== false ? $result : null;
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) as aggregate'];
        $clone->orderByColumn = null;
        $clone->limitValue = null;

        $result = $this->execute($clone->buildSelectSql(), $this->bindings)->fetch();
        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function buildSelectSql(): string
    {
        $columns = implode(', ', $this->columns);
        $sql = "SELECT {$columns} FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderByColumn !== null) {
            $sql .= " ORDER BY {$this->orderByColumn} {$this->orderByDirection}";
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        return $sql;
    }

    private function execute(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }
}