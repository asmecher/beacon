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
		$this->getCapsule()->schema()->create('endpoints', function($table) {
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
			$table->datetime('earliest_datestamp')->nullable();
			$table->string('repository_name')->nullable();
			$table->datetime('last_completed_update')->nullable();
			$table->integer('errors')->default(0);
			$table->mediumText('last_error')->nullable();
		});
		$this->getCapsule()->schema()->create('contexts', function($table) {
			$table->id();
			$table->foreignId('endpoint_id');
			$table->foreign('endpoint_id')->references('id')->on('endpoints');
			$table->string('set_spec');
			$table->string('issn')->nullable();
			$table->string('country')->nullable();
			$table->integer('total_record_count')->nullable();
			$table->datetime('last_completed_update')->nullable();
			$table->integer('errors')->default(0);
			$table->mediumText('last_error')->nullable();

			// There cannot be two contexts on the same endpoint with the same set specifier.
			$table->unique(['endpoint_id', 'set_spec']);
		});
		$this->getCapsule()->schema()->create('count_spans', function($table) {
			$table->id();
			$table->foreignId('context_id');
			$table->foreign('context_id')->references('id')->on('contexts');
			$table->string('label');
			$table->date('date_start');
			$table->date('date_end');
			$table->integer('record_count')->nullable();
			$table->datetime('date_counted');
			$table->mediumText('last_error')->nullable();

			// Each label must uniquely identify a count span for a given context.
			$table->unique(['context_id', 'label']);
		});
	}

	/**
	 * Drop the database schema.
	 */
	public function dropSchema() {
		$schema = $this->getCapsule()->schema();
		$schema->dropIfExists('count_spans');
		$schema->dropIfExists('contexts');
		$schema->dropIfExists('endpoints');
	}

	/**
	 * Flush the database contents
	 */
	public function flush() {
		$capsule = $this->getCapsule();
		$capsule()->table('count_spans')->delete();
		$capsule()->table('contexts')->delete();
		$capsule()->table('endpoints')->delete();
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
