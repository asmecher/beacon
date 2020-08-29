<?php

require_once('BeaconDatabase.inc.php');

class Contexts {
	protected $_db;

	function __construct(BeaconDatabase $db) {
		$this->_db = $db;
	}

	/**
	 * Get the list of installation entries.
	 * @return Iterator
	 */
	public function getAll() {
		return $this->_db->getCapsule()->table('contexts')
			->join('installations', 'contexts.installation_id', '=', 'installations.id')
			->select('contexts.*', 'installations.oai_url', 'installations.application', 'installations.version', 'installations.admin_email')
			->get();
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
	 * @param $installationId int Application ID
	 * @param $setSpec string OAI set specifier
	 * @return array New context entry.
	 */
	public function add($installationId, $setSpec) {
		$this->_db->getCapsule()->table('contexts')->insert($entry = [
			'set_spec' => $setSpec,
			'installation_id' => $installationId,
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
	 * Delete all contexts for an installation.
	 * @param $installationId int Numeric ID of the installation.
	 */
	public function flushByInstallationId(int $installationId) {
		$this->_db->getCapsule()->table('contexts')->where('installation_id', '=', $installationId)->delete();
	}
}
