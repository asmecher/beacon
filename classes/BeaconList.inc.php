<?php

class BeaconList {
	const FILENAME='beacon.csv';

	protected $_entries;

	protected $_fp = null;

	function __construct() {
		$this->load();
	}

	/**
	 * Determine a unique beacon ID from the specified application and set of query parameters
	 * @param $application string
	 * @param $query array
	 */
	public function getBeaconIdFromQuery($application, $query) {
		return $this->_makeBeaconId(
			$application,
			$query['id'] ?? null,
			$query['oai'] ?? null
		);
	}

	/**
	 * Determine a unique beacon ID from its entry
	 * @param $entry array
	 * @return string
	 */
	public function getBeaconIdFromEntry($entry) {
		return $this->_makeBeaconId(
			$entry['application'],
			$entry['statsId'],
			$entry['oaiUrl'],
		);
	}

	/**
	 * Build a unique (disambiguating) beacon ID from application, stats ID, and OAI URL
	 * @param $application string
	 * @param $statsId string
	 * @param $oaiUrl string
	 */
	protected function _makeBeaconId($application, $statsId = null, $oaiUrl = null) {
		if (empty($statsId) || empty($oaiUrl)) return null; // Not enough information; beacon probably disabled
		return $application . '-' . $statsId . '-' . $oaiUrl;
	}

	/**
	 * Load the entry list from CSV.
	 */
	public function load() {
		$this->_entries = [];
		$fp = fopen(self::FILENAME, 'r');
		if (!$fp) throw new Exception('Could not open beacon file ' . self::FILENAME . ' for reading.');
		if (!flock($fp, LOCK_SH)) throw new Exception ('Could not lock beacon file!');
		$columnNames = fgetcsv($fp);
		while ($row = fgetcsv($fp)) {
			$entry = array_combine($columnNames, $row);
			$this->_entries[$this->getBeaconIdFromEntry($entry)] = $entry;
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}

	/**
	 * Get the list of beacon entries.
	 * @return array
	 */
	public function getEntries() {
		return $this->_entries;
	}

	/**
	 * Find a beacon entry by ID.
	 * @param $beaconId string Beacon ID
	 * @return array|null Beacon entry, or null if not found.
	 */
	public function find($beaconId) {
		return $this->_entries[$beaconId] ?? null;
	}

	/**
	 * Get the count of unique beacon entries.
	 * @return int
	 */
	public function getCount() {
		return count($this->_entries);
	}

	/**
	 * Open the beacon list with an exclusive lock.
	 * @see BeaconList::saveLocked
	 */
	public function openLocked() {
		$this->_fp = fopen(self::FILENAME, 'r+');
		if (!$this->_fp) throw new Exception('Could not open beacon file ' . self::FILENAME . ' for appending.');
		if (!flock($this->_fp, LOCK_EX)) throw new Exception ('Could not lock beacon file!');
	}

	/**
	 * Save the beacon file previously opened with openLocked, and release the lock and close.
	 * @see BeaconList::openLocked
	 */
	public function saveLocked() {
		// Truncate the file after the header row.
		$columnNames = fgetcsv($this->_fp);
		ftruncate($this->_fp, ftell($this->_fp));
		foreach ($this->_entries as $entry) {
			fputcsv($this->_fp, array_map(function($key) use ($entry) {
				return $entry[$key]??null;
			}, $columnNames));
		}
		flock($this->_fp, LOCK_UN);
		fclose($this->_fp);
		$this->_fp = null;
	}

	/**
	 * Format a date/time.
	 * @param $time int|null UNIX domain time or NULL to use current time
	 * @return string
	 */
	static public function formatTime($time = null) {
		return strftime('%Y-%m-%d', $time ?? time());
	}

	/**
	 * Add a new entry from the specified query.
	 * @param $application string
	 * @param $query array
	 * @param $time int
	 * @return array New beacon entry.
	 */
	public function addFromQuery($application, $version, $query, $time) {
		$beaconId = $this->getBeaconIdFromQuery($application, $query);
		$entry = [
			'application' => $application,
			'version' => $version,
			'beaconId' => $beaconId,
			'oaiUrl' => $query['oai'],
			'statsId' => $query['id'],
			'firstBeacon' => $this->formatTime($time),
			'lastBeacon' => $this->formatTime($time),
			'lastOaiResponse' => null,
			'adminEmail' => null,
			'issn' => null,
			'country' => null,
			'totalRecordCount' => null,
			'lastCompletedUpdate' => null,
			'errors' => 0,
			'lastError' => null,
		];


		$this->_entries[$beaconId] = $entry;

		return $entry;
	}

	/**
	 * Update fields in an entry and save the resulting beacon list.
	 * @param $entry array The entry to update
	 * @param $fields array Optional extra data to include in the entry
	 */
	public function updateFields(array $entry, array $fields = []) {
		if (!$this->_fp) $this->load();
		$this->_entries[$entry['beaconId']] = array_merge(
			$entry,
			$fields
		);
		if (!$this->_fp) {
			$this->openLocked();
			$this->saveLocked();
		}
	}
}
