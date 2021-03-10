<?php

declare(strict_types=1);

namespace PKP\Beacon;

use PKP\Beacon\Entities\Entity;

class Exporter
{
    /** @var int Extracts information for the given year */
    public $year;
    /** @var int Filters out contexts that have less than the specified amount of records */
    public $minRecords;
    /** @var \SplFileObject The output file, should be writable */
    public $file;

    /**
     * Constructor
     *
     * @param \SplFileObject $file The output file, should be writable
     * @param int $year Extracts information for the given year
     * @param int $minRecords Filters out contexts that have less than the specified amount of records
     */
    public function __construct(\SplFileObject $file, int $year, int $minRecords)
    {
        $this->file = $file;
        $this->year = $year;
        $this->minRecords = $minRecords;
    }

    /**
     * Starts the processing
     */
    public function process(): void
    {
        $db = new Database();
        $capsule = $db->getCapsule();
        $query = $capsule->table('contexts')
            ->join('endpoints', 'contexts.endpoint_id', '=', 'endpoints.id')
            ->leftJoin('count_spans', function ($join) {
                $join->on('count_spans.context_id', '=', 'contexts.id')
                    ->where('count_spans.label', '=', $this->year);
            })
            ->select(
                $db->raw('GROUP_CONCAT(DISTINCT endpoints.oai_url SEPARATOR \'\n\') AS oai_url'),
                'endpoints.application',
                'endpoints.version',
                'endpoints.admin_email',
                'endpoints.earliest_datestamp',
                'endpoints.repository_name',
                'contexts.set_spec',
                $db->raw('COALESCE(MAX(count_spans.record_count), 0) AS record_count_' . $this->year),
                $db->raw('MAX(contexts.total_record_count) AS total_record_count'),
                $db->raw('MAX(contexts.issn) AS issn'),
                $db->raw('MAX(contexts.country) AS country'),
                $db->raw('MAX(endpoints.last_completed_update) AS last_completed_update'),
                $db->raw('MIN(first_beacon) AS first_beacon'),
                $db->raw('MAX(last_beacon) AS first_beacon'),
                $db->raw('MAX(last_oai_response) AS last_oai_response'),
                $db->raw('MAX(contexts.country_iso) AS country_iso'),
                $db->raw('MAX(endpoints.id) AS endpoint_id'),
                $db->raw('MAX(contexts.id) AS context_id'),
                $db->raw('MAX(count_spans.id) AS count_span_id')
            )
            ->where($db->raw('COALESCE(count_spans.record_count, 0)'), '>=', $this->minRecords)
            ->groupBy('endpoints.application', 'endpoints.version', 'endpoints.admin_email', 'endpoints.earliest_datestamp', 'endpoints.repository_name', 'contexts.set_spec');

        $keyFields = ['endpoints.id' => 'endpoint_id', 'contexts.id' => 'context_id', 'count_spans.id' => 'count_span_id'];
        foreach (Entity::paginateDynamically($query, $keyFields, 1000) as $i => $context) {
            $context = (array) $context;
            $context = array_slice($context, 0, count($context) - count($keyFields));
            if (!$i) {
                // Export column headers
                if (!$this->file->fputcsv(array_keys($context))) {
                    throw new \Exception('Unable to write output');
                }
            }

            // Export the table contents
            if (!$this->file->fputcsv($context)) {
                throw new \Exception('Unable to write output');
            }
        }
    }
}
