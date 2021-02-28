<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

class Contexts extends Entity
{
    /**
     * @copydoc Entity::getTableName()
     */
    protected function getTableName(): string
    {
        return 'contexts';
    }

    /**
     * Get the list of context entries.
     *
     * @param bool $randomOrder true iff the returned results should be randomly ordered; default false
     */
    public function getAll($randomOrder = false): iterable
    {
        $query = $this->db->getCapsule()
            ->table('contexts')
            ->join('endpoints', 'contexts.endpoint_id', '=', 'endpoints.id')
            ->select(
                'contexts.*',
                'endpoints.oai_url',
                'endpoints.application',
                'endpoints.version',
                'endpoints.admin_email',
                'endpoints.earliest_datestamp',
                'endpoints.repository_name',
                'endpoints.stats_id',
                'endpoints.first_beacon',
                'endpoints.last_beacon'
            );
        if ($randomOrder) {
            $query->inRandomOrder();
        }
        return $query->get();
    }

    /**
     * Delete all contexts for an endpoint.
     *
     * @param int $endpointId Numeric ID of the endpoint.
     */
    public function flushByEndpointId(int $endpointId): void
    {
        $this->db->getCapsule()->table('contexts')->where('endpoint_id', '=', $endpointId)->delete();
    }
}
