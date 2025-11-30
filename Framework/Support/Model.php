<?php

namespace Framework\Support;

use PDO;

abstract class Model
{
    protected PDO $pdo;

    protected string $table;

    public function __construct(Database $database)
    {
        $this->pdo = $database->getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM ' . $this->table);
        $results = $stmt->fetchAll();

        return $results ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function where(string $column, $value): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' WHERE ' . $column . ' = :value');
        $stmt->execute(['value' => $value]);

        $results = $stmt->fetchAll();

        return $results ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $columns = array_keys($data);
        $setClause = implode(', ', array_map(static fn($col) => $col . ' = :' . $col, $columns));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $this->table,
            $setClause
        );

        $stmt = $this->pdo->prepare($sql);

        $data['id'] = $id;

        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->table . ' WHERE id = :id');

        return $stmt->execute(['id' => $id]);
    }
}
