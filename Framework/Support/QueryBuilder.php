<?php

namespace Framework\Support;

use PDO;
use Framework\Support\Model;

class QueryBuilder
{
    private PDO $pdo;
    private string $table;
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private bool $withTrashed = false;
    private bool $onlyTrashed = false;

    public function __construct(string $table, PDO $pdo)
    {
        $this->table = $table;
        $this->pdo = $pdo;
    }

    /**
     * Add a WHERE condition.
     */
    public function where(string $column, $value, string $operator = '='): self
    {
        $param = ':' . count($this->bindings);
        $this->wheres[] = "$column $operator $param";
        $this->bindings[$param] = $value;
        return $this;
    }

    /**
     * Include soft-deleted rows.
     */
    public function withTrashed(): self
    {
        $this->withTrashed = true;
        return $this;
    }

    /**
     * Only soft-deleted rows.
     */
    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        return $this;
    }

    /**
     * Set ORDER BY.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * Set LIMIT.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Execute SELECT * and return all rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $whereClause = $this->buildWhereClause();
        if ($whereClause) {
            $sql .= " WHERE $whereClause";
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return first row.
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    private function buildWhereClause(): string
    {
        $clauses = $this->wheres;

        if (!$this->withTrashed) {
            $clauses[] = 'deleted_at IS NULL';
        }

        if ($this->onlyTrashed) {
            $clauses[] = 'deleted_at IS NOT NULL';
        }

        return implode(' AND ', $clauses);
    }
}
