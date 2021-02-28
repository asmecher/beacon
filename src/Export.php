<?php

declare(strict_types=1);

namespace PKP\Beacon;

class Export
{
    public $year;
    public $minRecords;
    public $file;

    public function __construct(\SplFileObject $file, int $year, int $minRecords)
    {
        $this->file = $file;
        $this->year = $year;
        $this->minRecords = $minRecords;
    }

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
                $db->escape('GROUP_CONCAT(DISTINCT endpoints.oai_url SEPARATOR \'\n\') AS oai_url'),
                'endpoints.application',
                'endpoints.version',
                'endpoints.admin_email',
                'endpoints.earliest_datestamp',
                'endpoints.repository_name',
                'contexts.set_spec',
                $db->escape('COALESCE(MAX(count_spans.record_count), 0) AS record_count_' . $this->year),
                $db->escape('MAX(contexts.total_record_count) AS total_record_count'),
                $db->escape('MAX(contexts.issn) AS issn'),
                $db->escape('MAX(contexts.country) AS country'),
                $db->escape('MAX(endpoints.last_completed_update) AS last_completed_update'),
                $db->escape('MIN(first_beacon) AS first_beacon'),
                $db->escape('MAX(last_beacon) AS first_beacon'),
                $db->escape('MAX(last_oai_response) AS last_oai_response')
            )
            ->where($db->escape('COALESCE(count_spans.record_count, 0)'), '>=', $this->minRecords)
            ->groupBy('endpoints.application', 'endpoints.version', 'endpoints.admin_email', 'endpoints.earliest_datestamp', 'endpoints.repository_name', 'contexts.set_spec');

        $records = $query->get();
        // Export column headers
        $context = $records->shift();
        if (!$context) {
            throw new \Exception('No beacon data is currently recorded');
        }
        if (!$this->file->fputcsv(array_keys((array) $context))) {
            throw new \Exception('Unable to write output');
        }

        // Export the table contents
        do {
            if (!$this->file->fputcsv((array) $context)) {
                throw new \Exception('Unable to write output');
            }
        } while ($context = $records->shift());
    }
}
