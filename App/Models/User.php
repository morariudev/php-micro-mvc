<?php

namespace App\Models;

use Framework\Support\Model;

class User extends Model
{
    protected string $table = 'users';

    /**
     * Columns that can be created/updated through mass assignment.
     *
     * @var array<int, string>
     */
    protected array $fillable = ['name', 'email'];

    /**
     * Optional: define primitive type casts for returned values.
     * e.g. ['id' => 'int']
     *
     * @var array<string, string>
     */
    protected array $casts = [
        'id' => 'int',
    ];

    /**
     * Safe create (filters $fillable).
     *
     * @param array<string, mixed> $data
     */
    public function createSafe(array $data): int
    {
        return $this->create($this->filterFillable($data));
    }

    /**
     * Safe update (filters $fillable).
     *
     * @param array<string, mixed> $data
     */
    public function updateSafe(int $id, array $data): bool
    {
        return $this->update($id, $this->filterFillable($data));
    }

    /**
     * Only allow fields that appear in $fillable.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        $allowed = [];

        foreach ($this->fillable as $column) {
            if (array_key_exists($column, $data)) {
                $allowed[$column] = $data[$column];
            }
        }

        return $allowed;
    }

    /**
     * Apply primitive type casts to a row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function castRow(array $row): array
    {
        foreach ($this->casts as $column => $type) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            switch ($type) {
                case 'int':
                    $row[$column] = (int) $row[$column];
                    break;
                case 'float':
                    $row[$column] = (float) $row[$column];
                    break;
                case 'bool':
                    $row[$column] = (bool) $row[$column];
                    break;
                case 'string':
                    $row[$column] = (string) $row[$column];
                    break;
            }
        }

        return $row;
    }

    /**
     * Override find() to apply casting.
     */
    public function find(int $id): ?array
    {
        $row = parent::find($id);
        return $row !== null ? $this->castRow($row) : null;
    }

    /**
     * Override all() to apply casting.
     */
    public function all(): array
    {
        return array_map(fn($row) => $this->castRow($row), parent::all());
    }

    /**
     * Override where() to apply casting.
     */
    public function where(string $column, $value): array
    {
        return array_map(fn($row) => $this->castRow($row), parent::where($column, $value));
    }
}
