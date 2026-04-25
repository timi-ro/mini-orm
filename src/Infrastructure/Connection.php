<?php

declare(strict_types=1);

namespace MiniOrm\Infrastructure;

use PDO;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

class Connection
{
    private PDO $pdo;

    private static array $requiredKeys = ['host', 'dbname', 'username', 'password'];

    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->pdo = $this->buildPdo($config);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function buildPdo(array $config): PDO
    {
        $host    = $config['host'];
        $port    = $config['port']    ?? 3306;
        $dbname  = $config['dbname'];
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return $pdo;
    }

    private function validateConfig(array $config): void
    {
        foreach (self::$requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                throw new InvalidArgumentException("Missing required database config key: '{$key}'");
            }
        }
    }
}
