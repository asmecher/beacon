<?php

require_once('Entity.inc.php');

class Endpoints extends Entity {
	/**
	 * @copydoc Entity::getTableName()
	 */
	protected function getTableName() {
		return 'endpoints';
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
	 * Get the count of unique endpoints.
	 * @return int
	 */
	public function getCount() {
		return $this->_db->getCapsule()->table('endpoints')->count();
	}
}
