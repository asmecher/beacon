<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

class Endpoints extends Entity
{
    /**
     * @copydoc Entity::getTableName()
     */
    protected function getTableName(): string
    {
        return 'endpoints';
    }

    /**
     * Determine a unique string from the specified application and set of query parameters
     */
    public function getDisambiguatorFromQuery(array $query): string
    {
        // WARNING: This will undercount endpoints that were duplicated e.g. for splitting journals!
        $url = $query['oai'] ?? null;
        $id = $query['id'] ?? null;
        if ($url === null || $url == '' || $id === null || $id == '') {
            return null;
        }

        // Clean up URL parts
        $url = preg_replace('/http[s]?:\/\//', '//', $url); // Make URL protocol-relative
        $url = preg_replace('/www\./', '//', $url); // Remove "www." from domain

        return $query['id'] . '-' . $url;
    }

    /**
     * Get the list of endpoint entries.
     */
    public function getAll(): iterable
    {
        return $this->db->getCapsule()->table('endpoints')->get();
    }

    /**
     * Get the count of unique endpoints.
     */
    public function getCount(): int
    {
        return $this->db->getCapsule()->table('endpoints')->count();
    }
}
