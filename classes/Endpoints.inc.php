<?php

require_once('BeaconDatabase.inc.php');

class Endpoints {
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
		// WARNING: This will undercount endpoints that were duplicated e.g. for splitting journals!
		$url = $query['oai']??null;
		if ($url === null || $url == '') return null;
		if (!isset($query['id']) || $query['id'] == '') return null;

		// Clean up URL parts
		$url = preg_replace('/http[s]?:\/\//','//', $url); // Make URL protocol-relative
		$url = preg_replace('/www\./','//', $url); // Remove "www." from domain

		return $query['id'] . '-' . $url;
	}

	/**
	 * Get the list of endpoint entries.
	 * @return Iterator
	 */
	public function getAll() {
		return $this->_db->getCapsule()->table('endpoints')->get();
	}

	/**
	 * Find an endpoint by disambiguator.
	 * @param $endpointId string Disambiguator
	 * @return array|null Endpoint characteristics, or null if not found.
	 */
	public function find($disambiguator) {
		return (array) $this->_db->getCapsule()->table('endpoints')->where('disambiguator', '=', $disambiguator)->get()->first();
	}

	/**
	 * Get the count of unique endpoints.
	 * @return int
	 */
	public function getCount() {
		return $this->_db->getCapsule()->table('endpoints')->count();
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
		$this->_db->getCapsule()->table('endpoints')->insert($entry = [
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
	 * @param $endpointId string The ID of the endpoint to update
	 * @param $fields array Optional extra data to include in the entry
	 */
	public function updateFields(int $endpointId, array $fields = []) {
		$this->_db->getCapsule()->table('endpoints')
			->where('id', '=', $endpointId)
			->update($fields);
	}
}
