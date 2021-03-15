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
     * Get the list of context entries that haven't been updated in a given amount of time.
     *
     * @param int $secondsInterval Amount of time to consider a record as updated
     */
    public function getOutdatedBy(int $secondsInterval, ?int $rows): \Generator
    {
        $secondsInterval = (int) $secondsInterval;
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
            )
            ->where($this->db->raw('COALESCE(TIMESTAMPDIFF(SECOND, contexts.last_completed_update, CURRENT_TIMESTAMP), ' . $secondsInterval . ')'), '>=', $secondsInterval);
        return $this->paginateDynamically($query, ['endpoints.id' => 'endpoint_id', 'contexts.id' => 'id'], $rows);
    }

    /**
     * Get the list of context entries that match the given OAI URL.
     */
    public function getByOaiUrl(string $oaiUrl, ?int $rows = null): \Generator
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
            )
            ->where('oai_url', '=', $oaiUrl);
        return $this->paginateDynamically($query, ['endpoints.id' => 'endpoint_id', 'contexts.id' => 'id'], $rows);
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
