<?php

require_once('Entity.inc.php');

class Contexts extends Entity {
	/**
	 * @copydoc Entity::getTableName()
	 */
	protected function getTableName() {
		return 'contexts';
	}

	/**
	 * Get the list of context entries.
	 * @param $randomOrder true iff the returned results should be randomly ordered; default false
	 * @return Iterator
	 */
	public function getAll($randomOrder = false) {
		$query = $this->_db->getCapsule()->table('contexts')
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
		if ($randomOrder) $query->inRandomOrder();
		return $query->get();
	}

	/**
	 * Delete all contexts for an endpoint.
	 * @param $endpointId int Numeric ID of the endpoint.
	 */
	public function flushByEndpointId(int $endpointId) {
		$this->_db->getCapsule()->table('contexts')->where('endpoint_id', '=', $endpointId)->delete();
	}
}
