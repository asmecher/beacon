<?php

declare(strict_types=1);

namespace PKP\Beacon;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    /** @var Capsule */
    protected $capsule;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->capsule = new Capsule();

        $this->capsule->addConnection([
            'driver' => 'mysql',
            'host' => getenv('BEACON_DBHOST') ?: 'localhost',
            'database' => getenv('BEACON_DBNAME') ?: 'beacon',
            'username' => getenv('BEACON_DBUSER') ?: 'beacon',
            'password' => getenv('BEACON_DBPASSWORD') ?: 'beacon',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ]);
        $this->capsule->setAsGlobal();
    }

    /**
     * Get the capsule.
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }

    /**
     * Create the database schema.
     */
    public function createSchema(): void
    {
        $schema = $this->getCapsule()->schema();
        $schema->create('endpoints', function ($table) {
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
        $schema->create('contexts', function ($table) {
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
        $schema->create('count_spans', function ($table) {
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
    public function dropSchema(): void
    {
        $schema = $this->getCapsule()->schema();
        $schema->dropIfExists('count_spans');
        $schema->dropIfExists('contexts');
        $schema->dropIfExists('endpoints');
    }

    /**
     * Flush the database contents
     */
    public function flush(): void
    {
        $capsule = $this->getCapsule();
        $capsule->table('count_spans')->delete();
        $capsule->table('contexts')->delete();
        $capsule->table('endpoints')->delete();
    }

    /**
     * Format a date/time.
     *
     * @param int|null $time UNIX domain time or NULL to use current time
     */
    public static function formatTime(?int $time = null): string
    {
        return strftime('%Y-%m-%d %T', $time ?? time());
    }

    public static function escape(string $data)
    {
        return Capsule::raw($data);
    }
}
