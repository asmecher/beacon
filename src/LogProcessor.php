<?php

declare(strict_types=1);

namespace PKP\Beacon;

use Kassner\LogParser\LogParser;
use PKP\Beacon\Entities\Endpoints;

class LogProcessor
{
    public const PATH_TO_APPLICATION_MAP = [
        '/ojs/xml/ojs-version.xml' => 'ojs',
        '/omp/xml/omp-version.xml' => 'omp',
        '/ops/xml/ops-version.xml' => 'ops',
    ];

    public const FILTERED_OAI_URLS = [
        '/localhost/',
        '/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', // IP addresses
        '/test/',
        '/demo[^s]/', // Avoid skipping Greek sociology
        '/theme/',
        '/\.softaculous\.com/', // Demos
    ];

    public const DEFAULT_LOG_FORMAT = '%h %l %u %t "%r" %>s %b "%{Referer}i" \"%{User-Agent}i"';

    private $db;
    private $endpoints;
    private $parser;
    private $logger;

    public function __construct(string $logFormat = self::DEFAULT_LOG_FORMAT, ?callable $logger = null)
    {
        $this->db = new Database();
        $this->endpoints = new Endpoints($this->db);
        $this->parser = new LogParser($logFormat);
        $this->logger = $logger;
    }

    public function process(\SplFileObject $file): array
    {
        $endpoints = $this->endpoints;
        $db = $this->db;

        $stats = [
            'ojsLogCount' => 0, 'ompLogCount' => 0, 'opsLogCount' => 0,
            'beaconDisabledLogCount' => 0, 'excludedCount' => 0,
            'totalBeaconCount' => 0, 'totalLogCount' => 0,
            'newAdditions' => 0, 'returningBeacons' => 0,
        ];

        // This is used to cache mappings from disambiguators to IDs.
        $disambiguatorIdCache = [];
        // This is used to store latest-encountered dates, batching updates at the end for performance.
        $newestDates = [];

        while ($line = $file->fgets()) {
            $stats['totalLogCount']++;

            // Optimization: If this substring isn't present, skip the entry immediately.
            if (!strstr($line, 'version.xml')) {
                continue;
            }

            $entry = $this->parser->parse($line);

            $matches = null;
            // Not a beacon
            if (!preg_match('/^GET (.*) HTTP\/1\.[01]$/', $entry->request, $matches)) {
                continue;
            }

            // Not a beacon
            if (!($url = parse_url($matches[1])) || !isset($url['path'])) {
                continue;
            }

            $application = self::PATH_TO_APPLICATION_MAP[$url['path']] ?? null;
            // It's not a beacon log entry; continue to next line
            if (!$application) {
                continue;
            }
            $stats[$application . 'LogCount']++;
            $stats['totalBeaconCount']++;
            if ($stats['totalBeaconCount'] % 100 == 0) {
                $this->log($stats['totalBeaconCount'] . ' beacons (' . $endpoints->getCount() . ' unique) on ' . $stats['totalLogCount'] . ' lines...');
            }

            parse_str($url['query'] ?? '', $query);
            if (!$disambiguator = $endpoints->getDisambiguatorFromQuery($query)) {
                // A unique ID could not be determined; count for stats and skip further processing.
                $stats['beaconDisabledLogCount']++;
                continue;
            }

            // Avoid excluded OAI URL forms
            foreach (self::FILTERED_OAI_URLS as $exclusion) {
                if (preg_match($exclusion, $query['oai'])) {
                    $stats['excludedCount']++;
                    continue 2;
                }
            }

            // Look for an existing endpoint ID by disambiguator (cached if possible).
            $endpointId = null;
            if (isset($disambiguatorIdCache[$disambiguator])) {
                $endpointId = $disambiguatorIdCache[$disambiguator];
            } elseif ($endpoint = $endpoints->find(['disambiguator' => $disambiguator])) {
                $endpointId = $endpoint['id'];
                $newestDates[$endpointId] = strtotime($endpoint['last_beacon']);
                $disambiguatorIdCache[$disambiguator] = $endpointId;
            }

            if (!$endpointId) {
                // Create a new beacon entry.
                $time = $db->formatTime(strtotime($entry->time));
                $endpoints->insert([
                    'application' => $application,
                    'version' => $entry->HeaderUserAgent,
                    'disambiguator' => $endpoints->getDisambiguatorFromQuery($query),
                    'oai_url' => $query['oai'],
                    'stats_id' => $query['id'],
                    'first_beacon' => $time,
                    'last_beacon' => $time,
                ]);
                $stats['newAdditions']++;
            } else {
                // Prepare to store the latest beacon ping date.
                $newestDates[$endpointId] = max(strtotime($entry->time), $newestDates[$endpointId]);
                $stats['returningBeacons']++;
            }
        }

        $this->log('Storing dates...');

        // Store the newest ping dates.
        foreach ($newestDates as $endpointId => $date) {
            $endpoints->update($endpointId, ['last_beacon' => $db->formatTime($date)]);
        }

        return $stats;
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
