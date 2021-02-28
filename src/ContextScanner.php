<?php

declare(strict_types=1);

namespace PKP\Beacon;

use PKP\Beacon\Entities\Endpoints;
use PKP\Beacon\Tasks\ContextScannerTask;
use PKP\Beacon\Tasks\TaskManager;
use Spatie\Async\Pool;

class ContextScanner extends TaskManager
{
    private $logger;

    public $minimumSecondsBetweenUpdates = Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES;
    public $requestTimeout;
    public $oai;

    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger;
    }

    public function process(): void
    {
        $db = new Database();
        $endpoints = new Endpoints($db);

        $statistics = ['selected' => 0, 'skipped' => 0, 'total' => 0];

        $this->log('Queuing processes...');
        foreach ($endpoints->getAll() as $endpoint) {
            $endpoint = (array) $endpoint;

            // Select only the desired endpoint for task queueing.
            $statistics['total']++;
            if (!empty($this->oai)) {
                // If a specific OAI URL was specified, update that record.
                if ($endpoint['oai_url'] != $this->oai) {
                    $statistics['skipped']++;
                    continue;
                } else {
                    $statistics['selected']++;
                }
            } else {
                // Default: skip anything that was updated successfully in the last "DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES".
                if ($endpoint['last_completed_update'] && time() - strtotime($endpoint['last_completed_update']) < $this->minimumSecondsBetweenUpdates) {
                    $statistics['skipped']++;
                    continue;
                }
            }
            $this
                ->addTask(new ContextScannerTask($endpoint, $this->requestTimeout))
                ->catch(function (\Exception $e) {
                    // Watch for errors, particularly child process out-of-memory problems.
                    $this->log('Caught child process exception: ' . $e->getMessage());
                });
        }

        $this->log('Finished queueing. Statistics: ' . print_r($statistics, true) . 'Running queue...');

        $this->run(function (Pool $pool) {
            $this->log(str_replace("\n", '', $pool->status()));
        });

        $this->log('Finished');
        if ($this->oai) {
            foreach ($endpoints->getAll() as $endpoint) {
                $endpoint = (array) $endpoint;
                if ($endpoint['oai_url'] == $this->oai) {
                    $this->log(print_r($endpoint, 1));
                }
            }
        }
    }

    private function log(string $message): void
    {
        if ($this->logger) {
            ($this->logger)($message);
        }
    }
}
