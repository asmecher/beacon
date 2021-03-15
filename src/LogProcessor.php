<?php

declare(strict_types=1);

namespace PKP\Beacon;

use Kassner\LogParser\LogParser;
use PKP\Beacon\Entities\Endpoints;

class LogProcessor
{
    public const DEFAULT_LOG_FORMAT = '%h %l %u %t "%m %U %H" %>s %b "%{Referer}i" \"%{User-Agent}i"';

    /** @var callable|null Logger function, receives a string */
    public $logger;

    /**
     * Starts the processing of the given log file
     *
     * @param string $logFormat The formatting rules (https://github.com/kassner/log-parser#supported-format-strings) of the log file
     */
    public function process(\SplFileObject $file, string $logFormat = self::DEFAULT_LOG_FORMAT): void
    {
        $fileSize = 0;
        try {
            $fileSize = $file->getSize();
        } catch (\Exception $e) {
            // Probably an stream
        }

        $db = new Database();
        $endpoints = new Endpoints($db);
        $parser = new LogParser($logFormat);

        $statistics = (object) [
            'ojsLogCount' => 0,
            'ompLogCount' => 0,
            'opsLogCount' => 0,
            'beaconDisabledLogCount' => 0,
            'excludedCount' => 0,
            'totalBeaconCount' => 0,
            'totalLogCount' => 0,
            'newAdditions' => 0,
            'returningBeacons' => 0,
        ];

        // This is used to cache mappings from disambiguators to IDs.
        $disambiguatorIdCache = [];
        // This is used to store latest-encountered dates, batching updates at the end for performance.
        $newestDates = [];
        $unique = $endpoints->getCount();
        $this->log('Parsing log...');
        while (!$file->eof()) {
            $line = $file->fgets();
            $statistics->totalLogCount++;

            try {
                $logEntry = $parser->parse($line);
            } catch (\Exception $e) {
                $this->log("\nFailed to parse log line #" . $statistics->totalLogCount);
                continue;
            }

            $url = (object) parse_url($logEntry->URL);
            // It's not a beacon log entry; continue to next line
            if (!$application = $endpoints->getApplicationFromPath($url->path ?? '')) {
                continue;
            }

            $application = strtolower($application);
            $statistics->{$application . 'LogCount'}++;
            if (!($statistics->totalBeaconCount++ % 100) || $file->eof()) {
                $this->log('Status: ' . ($fileSize ? round($file->ftell() / $fileSize * 100) . '% - ' : '') . $statistics->totalBeaconCount . ' beacons (' . $unique . ' unique) on ' . $statistics->totalLogCount . ' lines.', true);
            }

            parse_str($url->query ?? '', $query);
            $query = (object) $query;
            if (!$disambiguator = $endpoints->getDisambiguatorFromQuery($query)) {
                // A unique ID could not be determined; count for stats and skip further processing.
                $statistics->beaconDisabledLogCount++;
                continue;
            }

            // Avoid excluded OAI URL forms
            if (!$endpoints->isValidOaiUrl($query->oai)) {
                $statistics->excludedCount++;
                continue;
            }

            // Look for an existing endpoint ID by disambiguator (cached if possible).
            if (!$endpointId = $disambiguatorIdCache[$disambiguator] ?? null) {
                if ($endpoint = $endpoints->find(['disambiguator' => $disambiguator])) {
                    $endpointId = $endpoint->id;
                    $newestDates[$endpointId] = strtotime($endpoint->last_beacon);
                    $disambiguatorIdCache[$disambiguator] = $endpointId;
                }
            }

            // If the endpoint wasn't found, insert it
            if (!$endpointId) {
                $time = $db->formatTime(strtotime($logEntry->time));
                $endpoints->insert([
                    'application' => $application,
                    'version' => $logEntry->HeaderUserAgent,
                    'disambiguator' => $disambiguator,
                    'oai_url' => $query->oai,
                    'stats_id' => $query->id,
                    'first_beacon' => $time,
                    'last_beacon' => $time,
                ]);
                ++$unique;
                $statistics->newAdditions++;
            } else {
                // Prepare to store the latest beacon ping date
                $newestDates[$endpointId] = max(strtotime($logEntry->time), $newestDates[$endpointId]);
                $statistics->returningBeacons++;
            }
        }

        $this->log("\nStoring dates...");

        // Store the newest ping dates.
        $i = 0;
        $total = count($newestDates);
        foreach ($newestDates as $endpointId => $date) {
            $this->log('Storing ' . ++$i . '/' . $total, true);
            $endpoints->update($endpointId, ['last_beacon' => $db->formatTime($date)]);
        }

        $this->log("\nStatistics:");
        foreach ($statistics as $key => $value) {
            $this->log("{$key}: {$value}");
        }
    }

    /**
     * Logs the given text
     *
     * @param bool|null $replace If replace is true, a carriage-return will be added to the end, otherwise a new line
     */
    private function log(string $message, ?bool $replace = false): void
    {
        if ($this->logger) {
            ($this->logger)($message . ($replace ? "\r" : "\n"));
        }
    }
}
