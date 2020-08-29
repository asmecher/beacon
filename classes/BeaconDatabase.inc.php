<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class BeaconDatabase {
	protected $_capsule;
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_capsule = new Capsule;

		$this->_capsule->addConnection([
			'driver' => 'mysql',
			'host' => getenv('BEACON_DBHOST')?:'localhost',
			'database' => getenv('BEACON_DBNAME')?:'beacon',
			'username' => getenv('BEACON_DBUSER')?:'beacon',
			'password' => getenv('BEACON_DBPASSWORD')?:'beacon',
			'charset' => 'utf8',
			'collation' => 'utf8_unicode_ci',
			'prefix' => '',
		]);
		$this->_capsule->setAsGlobal();
	}

	/**
	 * Get the capsule.
	 * @return Capsule
	 */
	public function getCapsule() {
		return $this->_capsule;
	}

	/**
	 * Create the database schema.
	 */
	public function createSchema() {
		$this->getCapsule()->schema()->create('installations', function($table) {
			$table->id();
			$table->string('application');
			$table->string('version');
			$table->string('disambiguator')->unique();
			$table->string('oai_url');
			$table->string('stats_id');
			$table->datetime('first_beacon');
			$table->datetime('last_beacon');
			$table->datetime('last_oai_response')->nullable();
			$table->string('admin_email')->nullable();
			$table->datetime('last_completed_update')->nullable();
			$table->integer('errors')->default(0);
			$table->mediumText('last_error')->nullable();
		});
		$this->getCapsule()->schema()->create('contexts', function($table) {
			$table->id();
			$table->foreignId('installation_id');
			$table->foreign('installation_id')->references('id')->on('installations');
			$table->string('set_spec');
			$table->string('issn')->nullable();
			$table->string('country')->nullable();
			$table->integer('total_record_count')->nullable();
			$table->datetime('last_completed_update')->nullable();
			$table->integer('errors')->default(0);
			$table->mediumText('last_error')->nullable();
		});
	}

	/**
	 * Drop the database schema.
	 */
	public function dropSchema() {
		$this->getCapsule()->schema()->dropIfExists('contexts');
		$this->getCapsule()->schema()->dropIfExists('installations');
	}

	/**
	 * Flush the database contents
	 */
	public function flush() {
		$this->getCapsule()->table('contexts')->delete();
		$this->getCapsule()->table('installations')->delete();
	}

	/**
	 * Format a date/time.
	 * @param $time int|null UNIX domain time or NULL to use current time
	 * @return string
	 */
	static public function formatTime($time = null) {
		return strftime('%Y-%m-%d %T', $time ?? time());
	}
}
