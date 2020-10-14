<?php

require_once('BeaconDatabase.inc.php');

class Contexts {
	protected $_db;

	function __construct(BeaconDatabase $db) {
		$this->_db = $db;
	}

	/**
	 * Get the list of context entries.
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
	 * Find a context by ID.
	 * @param $id int Context ID
	 * @return array|null Context characteristics, or null if not found.
	 */
	public function find(int $id) {
		return (array) $this->_db->getCapsule()->table('contexts')->where('id', '=', $id)->get()->first();
	}

	/**
	 * Add a new entry from the specified query.
	 * @param $endpointId int Application ID
	 * @param $setSpec string OAI set specifier
	 * @return array New context entry.
	 */
	public function add($endpointId, $setSpec) {
		$this->_db->getCapsule()->table('contexts')->insert($entry = [
			'set_spec' => $setSpec,
			'endpoint_id' => $endpointId,
		]);
		return $entry;
	}

	/**
	 * Update fields in an entry and save the resulting beacon list.
	 * @param $contextId int The ID of the context to update
	 * @param $fields array Optional extra data to include in the entry
	 */
	public function updateFields(string $contextId, array $fields = []) {
		$this->_db->getCapsule()->table('contexts')
			->where('id', '=', $contextId)
			->update($fields);
	}

	/**
	 * Delete all contexts for an endpoint.
	 * @param $endpointId int Numeric ID of the endpoint.
	 */
	public function flushByEndpointId(int $endpointId) {
		$this->_db->getCapsule()->table('contexts')->where('endpoint_id', '=', $endpointId)->delete();
	}
}
