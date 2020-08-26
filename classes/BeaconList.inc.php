<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class BeaconList {
	/**
	 * Constructor
	 */
	public function __construct() {
		$capsule = new Capsule;

		$capsule->addConnection([
			'driver' => 'mysql',
			'host' => 'localhost',
			'database' => 'beacon',
			'username' => 'beacon',
			'password' => 'beacon',
			'charset' => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix' => '',
		]);
		$capsule->setAsGlobal();
	}

	/**
	 * Create the database schema.
	 */
	public function createSchema() {
		Capsule::schema()->create('entries', function($table) {
			$table->increments('id');
			$table->string('application');
			$table->string('version');
			$table->string('beacon_id')->unique();
			$table->string('oai_url');
			$table->string('stats_id');
			$table->datetime('first_beacon');
			$table->datetime('last_beacon');
			$table->datetime('last_oai_response')->nullable();
			$table->string('admin_email')->nullable();
			$table->string('issn')->nullable();
			$table->string('country')->nullable();
			$table->integer('total_record_count')->nullable();
			$table->datetime('last_completed_update')->nullable();
			$table->integer('errors')->default(0);
			$table->mediumText('last_error')->nullable();
		});
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
	 * Get the list of beacon entries.
	 * @return Iterator
	 */
	public function getEntries() {
		return Capsule::table('entries')->get();
	}

	/**
	 * Find a beacon entry by ID.
	 * @param $beaconId string Beacon ID
	 * @return array|null Beacon entry, or null if not found.
	 */
	public function find($beaconId) {
		return (array) Capsule::table('entries')->where('beacon_id', '=', $beaconId)->get()->first();
	}

	/**
	 * Get the count of unique beacon entries.
	 * @return int
	 */
	public function getCount() {
		return Capsule::table('entries')->count();
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
		Capsule::table('entries')->insert($entry = [
			'application' => $application,
			'version' => $version,
			'beacon_id' => $beaconId,
			'oai_url' => $query['oai'],
			'stats_id' => $query['id'],
			'first_beacon' => $this->formatTime($time),
			'last_beacon' => $this->formatTime($time),
			'last_oai_response' => null,
			'admin_email' => null,
			'issn' => null,
			'country' => null,
			'total_record_count' => null,
			'last_completed_update' => null,
			'errors' => 0,
			'last_error' => null,
		]);
		return $entry;
	}

	/**
	 * Update fields in an entry and save the resulting beacon list.
	 * @param $beaconId string The beacon ID of the entry to update
	 * @param $fields array Optional extra data to include in the entry
	 */
	public function updateFields(string $beaconId, array $fields = []) {
		Capsule::table('entries')
			->where('beacon_id', '=', $beaconId)
			->update($fields);
	}
}
