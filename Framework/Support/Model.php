<?php

namespace Framework\Support;

use PDO;
use DateTime;

abstract class Model
{
    protected PDO $pdo;
    protected string $table;
    protected bool $timestamps = true;
    protected bool $softDeletes = true;

    public function __construct(Database $database)
    {
        $this->pdo = $database->getConnection();
    }

    /**
     * Start a new query builder.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder($this->table, $this->pdo);
    }

    /**
     * Find by primary key.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id" .
            ($this->softDeletes ? " AND deleted_at IS NULL" : "") .
            " LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Retrieve all records.
     */
    public function all(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM {$this->table}" .
            ($this->softDeletes ? " WHERE deleted_at IS NULL" : "")
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Where condition helper.
     */
    public function where(string $column, $value): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} = :value" .
            ($this->softDeletes ? " AND deleted_at IS NULL" : "")
        );
        $stmt->execute(['value' => $value]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create a new record (with timestamps)
     */
    public function create(array $data): int
    {
        if ($this->timestamps) {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update record by id (with updated_at)
     */
    public function update(int $id, array $data): bool
    {
        if ($this->timestamps) {
            $data['updated_at'] = (new DateTime())->format('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $setClause = implode(', ', array_map(fn($c) => "$c = :$c", $columns));
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $data['id'] = $id;

        return $stmt->execute($data);
    }

    /**
     * Delete a record (soft delete or hard delete)
     */
    public function delete(int $id): bool
    {
        if ($this->softDeletes) {
            $stmt = $this->pdo->prepare(
                "UPDATE {$this->table} SET deleted_at = :deleted_at WHERE id = :id"
            );
            return $stmt->execute([
                'deleted_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'id' => $id
            ]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Restore a soft-deleted record.
     */
    public function restore(int $id): bool
    {
        if (!$this->softDeletes) return false;
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET deleted_at = NULL WHERE id = :id"
        );
        return $stmt->execute(['id' => $id]);
    }
}
