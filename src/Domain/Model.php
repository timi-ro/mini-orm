<?php

declare(strict_types=1);

namespace MiniOrm\Domain;

use MiniOrm\Core\QueryBuilder;
use MiniOrm\Infrastructure\Database;
use ReflectionClass;
use RuntimeException;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    protected array $attributes = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    // -------------------------------------------------------------------------
    // Static query entry points
    // -------------------------------------------------------------------------

    public static function query(): ModelQuery
    {
        return new ModelQuery(static::newQuery(), static::class);
    }

    /** @return static|null */
    public static function find(int|string $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    /** @return list<static> */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Returns a chainable ModelQuery — call ->get(), ->first(), ->count(), etc. to execute.
     *
     * User::where('active', 1)->orderBy('name')->get();
     */
    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): ModelQuery
    {
        $mq = static::query();

        if (func_num_args() === 2) {
            return $mq->where($column, $operatorOrValue);
        }

        return $mq->where($column, $operatorOrValue, $value);
    }

    public static function create(array $attributes): static
    {
        $id = static::newQuery()->insert($attributes);
        $attributes[static::$primaryKey] = $id;

        return static::fromRow($attributes);
    }

    // -------------------------------------------------------------------------
    // Instance mutation methods
    // -------------------------------------------------------------------------

    public function update(array $attributes): bool
    {
        $this->guardExists('update');

        $affected = static::newQuery()
            ->where(static::$primaryKey, $this->getAttribute(static::$primaryKey))
            ->update($attributes);

        if ($affected > 0) {
            $this->attributes = array_merge($this->attributes, $attributes);
        }

        return $affected > 0;
    }

    public function delete(): bool
    {
        $this->guardExists('delete');

        $affected = static::newQuery()
            ->where(static::$primaryKey, $this->getAttribute(static::$primaryKey))
            ->delete();

        if ($affected > 0) {
            $this->exists = false;
        }

        return $affected > 0;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    public static function fromRow(array $row): static
    {
        $instance         = new static($row);
        $instance->exists = true;
        return $instance;
    }

    protected static function newQuery(): QueryBuilder
    {
        return (new QueryBuilder(Database::getDefault()))
            ->table(static::resolveTable());
    }

    protected static function resolveTable(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        $shortName = (new ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName)) . 's';
    }

    private function guardExists(string $operation): void
    {
        if (!$this->exists) {
            throw new RuntimeException(
                "Cannot {$operation} a model that does not exist in the database."
            );
        }
    }
}