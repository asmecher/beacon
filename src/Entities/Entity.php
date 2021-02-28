<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

use PKP\Beacon\Database;

abstract class Entity
{
    /** @var Database */
    protected $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find a single entry by its specified characteristics.
     *
     * @param array $characteristics [column_name => value, ...]
     *
     * @return array|null Context characteristics, or null if not found.
     */
    public function find(array $characteristics): ?array
    {
        $query = $this->db->getCapsule()->table($this->getTableName());
        foreach ($characteristics as $name => $value) {
            $query->where($name, '=', $value);
        }
        return (array) $query->get()->first();
    }

    /**
     * Add a new entry.
     *
     * @return array New entry.
     */
    public function insert(array $entry): array
    {
        $this->db->getCapsule()->table($this->getTableName())->insert($entry);
        return $entry;
    }

    /**
     * Update fields in an entry.
     *
     * @param $id int The ID of the entry to update
     * @param $fields array Data to include in the entry
     */
    public function update(int $id, array $fields): void
    {
        $this->db->getCapsule()->table($this->getTableName())
            ->where('id', '=', $id)
            ->update($fields);
    }

    /**
     * Get the name of the database table for this entity.
     */
    abstract protected function getTableName(): string;
}
