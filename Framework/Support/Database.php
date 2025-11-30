<?php

namespace Framework\Support;

use PDO;
use PDOException;

class Database
{
    private PDO $connection;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver !== 'sqlite') {
            throw new \InvalidArgumentException('Only sqlite driver is supported in this demo.');
        }

        $dsn = 'sqlite:' . ($config['database'] ?? ':memory:');

        try {
            $this->connection = new PDO($dsn);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
