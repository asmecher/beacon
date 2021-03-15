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
        $baseQuery = clone $query;
        $page = 0;
        do {
            $page = $baseQuery->simplePaginate($rows, ['*'], '', ++$page);
            foreach ($page as $row) {
                yield $row;
            }
        } while ($page->hasMorePages());
    }

    /**
     * Given a Laravel query builder and the amount of rows, it creates a Laravel paginator and lazily retrieve all the rows using a generator.
     * The code always retrieve the *first page*! To advance to the next page, it keeps track of the last processed record using the $keyFields argument
     * Which means it has some requirements:
     * - The query must be sorted only by the given keys (done automatically by the function)
     * - They key values should be unique, non-null and strictly comparable ('a' > 'A' returning false is a showstopper!)
     * - The keys should be present in the "select"
     *
     * @param array $keyFields This is used to tell the paginator which fields should be used to keep track of the last processed record.
     *  The key must be set to the field name available in the query, and the value must map to the field name expressed in the select
     */
    public static function paginateDynamically(Builder $query, array $keyFields, int $rows = Constants::DEFAULT_PAGE_SIZE): \Generator
    {
        // Cloning to avoid side-effects
        $baseQuery = clone $query;
        $lastField = end($keyFields);
        $clause = '';
        // Build a filter clause to retrieve the "next pages" based on the last retrieved record
        foreach ($keyFields as $dbField => $objectField) {
            $baseQuery->orderBy($dbField);
            $clause .= "${dbField} > ?" . ($lastField != $objectField ? " OR (${dbField} = ? AND (" : '');
        }
        $clause = '(' . $clause . str_repeat(')', 2 * (count($keyFields) - 1)) . ')';
        $keys = null;
        do {
            $query = clone $baseQuery;
            // If it's a next page request
            if ($keys) {
                $bindings = [];
                // Feed the parameters of the filter clause
                foreach ($keys as $field => $value) {
                    $bindings[] = $value;
                    if ($field != $lastField) {
                        $bindings[] = $value;
                    }
                }
                $query->whereRaw($clause, $bindings);
            }
            // We could use the limit here, but the simplePaginate already has the logic to retrieve one extra row (to check if there's more pages ahead...)
            $results = $query->simplePaginate($rows);
            $row = null;
            foreach ($results as $row) {
                yield $row;
            }
            // Save the keys of the last retrieved row
            if ($row) {
                $keys = [];
                foreach ($keyFields as $objectField) {
                    $keys[$objectField] = $row->$objectField;
                }
            }
        } while ($results->hasMorePages());
    }
}
