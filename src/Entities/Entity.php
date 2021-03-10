<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

use Illuminate\Database\Query\Builder;
use PKP\Beacon\Constants;
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
     * @return object|null Context characteristics, or null if not found.
     */
    public function find(array $characteristics): ?object
    {
        $query = $this->db->getCapsule()->table($this->getTableName());
        foreach ($characteristics as $name => $value) {
            $query->where($name, '=', $value);
        }
        return $query->get()->first();
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


    /**
     * Given a Laravel query builder and the amount of rows, it creates a Laravel paginator and lazily retrieve all the rows using a generator.
     * As the resultset is paged, be sure to use a good sorting method, to avoid visiting the same record in a next page.
     */
    public static function paginateLazily(Builder $query, int $rows = Constants::DEFAULT_PAGE_SIZE): \Generator
    {
        $page = 0;
        do {
            $results = $query->simplePaginate($rows, ['*'], '', ++$page);
            foreach ($results as $row) {
                yield $row;
            }
        } while ($results->hasMorePages());
    }
}
