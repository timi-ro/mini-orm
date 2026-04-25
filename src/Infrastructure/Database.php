<?php

declare(strict_types=1);

namespace MiniOrm\Infrastructure;

use RuntimeException;

class Database
{
    /** @var Connection[] */
    private static array $connections = [];

    private static string $defaultName = 'default';

    private function __construct() {}

    public static function configure(array $config, string $name = 'default'): void
    {
        self::$connections[$name] = new Connection($config);
    }

    public static function connection(string $name = 'default'): Connection
    {
        if (!isset(self::$connections[$name])) {
            throw new RuntimeException("No database connection configured with name: '{$name}'");
        }

        return self::$connections[$name];
    }

    public static function setDefault(string $name): void
    {
        if (!isset(self::$connections[$name])) {
            throw new RuntimeException("Cannot set default: no connection named '{$name}' exists");
        }

        self::$defaultName = $name;
    }

    public static function getDefault(): Connection
    {
        return self::connection(self::$defaultName);
    }

    /** For test environments only — resets all registered connections. */
    public static function reset(): void
    {
        self::$connections = [];
        self::$defaultName = 'default';
    }
}
