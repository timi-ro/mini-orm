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
        // Use func_num_args() to distinguish "operator omitted" from "value is null"
        if (func_num_args() === 2) {
            $operator = '=';
            $value    = $operatorOrValue;
        } else {
            $operator = strtoupper((string) $operatorOrValue);
            if (!in_array($operator, self::$allowedOperators, true)) {
                throw new InvalidArgumentException("Unsupported operator: '{$operator}'");
            }
        }

        $quoted = $this->quoteIdentifier($column);

        if ($value === null) {
            $this->wheres[] = match ($operator) {
                '='          => "{$quoted} IS NULL",
                '!=', '<>'   => "{$quoted} IS NOT NULL",
                default      => throw new InvalidArgumentException(
                    "Operator '{$operator}' cannot be used with NULL; use whereNull() / whereNotNull()."
                ),
            };
        } else {
            $this->wheres[]   = "{$quoted} {$operator} ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = $this->quoteIdentifier($column) . ' IS NULL';
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = $this->quoteIdentifier($column) . ' IS NOT NULL';
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $upper = strtoupper($direction);
        if ($upper !== 'ASC' && $upper !== 'DESC') {
            throw new InvalidArgumentException("Order direction must be ASC or DESC, got '{$direction}'");
        }

        $this->orderByColumn    = $column;
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
        $clone            = clone $this;
        $clone->limitValue = 1;

        $result = $clone->execute($clone->buildSelectSql(), $clone->bindings)->fetch();
        return $result !== false ? $result : null;
    }

    public function count(): int
    {
        $clone                = clone $this;
        $clone->columns       = ['COUNT(*) as aggregate'];
        $clone->orderByColumn = null;
        $clone->limitValue    = null;

        $result = $clone->execute($clone->buildSelectSql(), $clone->bindings)->fetch();
        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        $clone                = clone $this;
        $clone->columns       = ['1'];
        $clone->orderByColumn = null;
        $clone->limitValue    = 1;

        $result = $clone->execute($clone->buildSelectSql(), $clone->bindings)->fetch();
        return $result !== false;
    }

    public function insert(array $values): int
    {
        if (empty($values)) {
            throw new InvalidArgumentException('insert() requires at least one column-value pair.');
        }

        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('insert() keys must be string column names.');
            }
        }

        $columns      = implode(', ', array_map($this->quoteIdentifier(...), array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql          = 'INSERT INTO ' . $this->quoteIdentifier($this->table)
                      . " ({$columns}) VALUES ({$placeholders})";

        $this->execute($sql, array_values($values));
        return (int) $this->connection->getPdo()->lastInsertId();
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            throw new InvalidArgumentException('update() requires at least one column-value pair.');
        }

        foreach (array_keys($values) as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('update() keys must be string column names.');
            }
        }

        $sets     = implode(', ', array_map(
            fn($col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($values)
        ));
        $sql      = 'UPDATE ' . $this->quoteIdentifier($this->table) . " SET {$sets}";
        $bindings = array_values($values);

        if (!empty($this->wheres)) {
            $sql      .= ' WHERE ' . implode(' AND ', $this->wheres);
            $bindings  = array_merge($bindings, $this->bindings);
        }

        return $this->execute($sql, $bindings)->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($this->table);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $this->execute($sql, $this->bindings)->rowCount();
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function buildSelectSql(): string
    {
        $columns = implode(', ', $this->columns);
        $sql     = "SELECT {$columns} FROM " . $this->quoteIdentifier($this->table);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        if ($this->orderByColumn !== null) {
            $sql .= ' ORDER BY ' . $this->quoteIdentifier($this->orderByColumn)
                  . " {$this->orderByDirection}";
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

    private function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);

        foreach ($parts as $part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new InvalidArgumentException("Invalid SQL identifier: '{$part}'");
            }
        }

        return implode('.', array_map(fn($p) => '`' . $p . '`', $parts));
    }
}