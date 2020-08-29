<?php

require_once('BeaconDatabase.inc.php');

class Installations {
	protected $_db;

	function __construct(BeaconDatabase $db) {
		$this->_db = $db;
	}

	/**
	 * Determine a unique string from the specified application and set of query parameters
	 * @param $application string
	 * @param $query array
	 */
	public function getDisambiguatorFromQuery($query) {
		// WARNING: This will undercount installations that were duplicated e.g. for splitting journals!
		return $query['id'] ?? null;
	}

	/**
	 * Get the list of installation entries.
	 * @return Iterator
	 */
	public function getAll() {
		return $this->_db->getCapsule()->table('installations')->get();
	}

	/**
	 * Find an installation by disambiguator.
	 * @param $installationId string Disambiguator
	 * @return array|null Installation characteristics, or null if not found.
	 */
	public function find($disambiguator) {
		return (array) $this->_db->getCapsule()->table('installations')->where('disambiguator', '=', $disambiguator)->get()->first();
	}

	/**
	 * Get the count of unique installations.
	 * @return int
	 */
	public function getCount() {
		return $this->_db->getCapsule()->table('installations')->count();
	}

	/**
	 * Add a new entry from the specified query.
	 * @param $application string
	 * @param $query array
	 * @param $time int
	 * @return array New beacon entry.
	 */
	public function addFromQuery($application, $version, $query, $time) {
		$disambiguator = $this->getDisambiguatorFromQuery($query);
		$this->_db->getCapsule()->table('installations')->insert($entry = [
			'application' => $application,
			'version' => $version,
			'disambiguator' => $disambiguator,
			'oai_url' => $query['oai'],
			'stats_id' => $query['id'],
			'first_beacon' => $this->_db->formatTime($time),
			'last_beacon' => $this->_db->formatTime($time),
		]);
		return $entry;
	}

	/**
	 * Update fields in an entry and save the resulting beacon list.
	 * @param $installationId string The ID of the installation to update
	 * @param $fields array Optional extra data to include in the entry
	 */
	public function updateFields(int $installationId, array $fields = []) {
		$this->_db->getCapsule()->table('installations')
			->where('id', '=', $installationId)
			->update($fields);
	}
}
