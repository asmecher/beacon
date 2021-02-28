<?php

declare(strict_types=1);

namespace PKP\Beacon;

use PKP\Beacon\Entities\Contexts;
use PKP\Beacon\Tasks\ContextSynchronizerTask;
use PKP\Beacon\Tasks\TaskManager;
use Spatie\Async\Pool;

class ContextSynchronizer extends TaskManager
{
    private $logger;

    public $minimumSecondsBetweenUpdates = Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES;
    public $requestTimeout;
    public $oaiUrl;
    public $year;

    public function __construct(?callable $logger = null)
    {
        $this->logger = $logger;
    }

    public function process(): void
    {
        $this->log('Queuing processes...');

        $db = new Database();
        $contexts = new Contexts($db);
        $statistics = ['selected' => 0, 'skipped' => 0, 'total' => 0];

        foreach ($contexts->getAll(true) as $context) {
            $context = (array) $context;

            // Select only the desired entries for task queueing.
            $statistics['total']++;
            if (!empty($this->oaiUrl)) {
                // If a specific OAI URL was specified, update that record.
                if ($context['oai_url'] != $this->oaiUrl) {
                    $statistics['skipped']++;
                    continue;
                } else {
                    $statistics['selected']++;
                }
            } else {
                // Default: skip anything that was recently updated successfully.
                if ($context['last_completed_update'] && time() - strtotime($context['last_completed_update']) < $this->minimumSecondsBetweenUpdates) {
                    $statistics['skipped']++;
                    continue;
                }
            }

            $this
                ->addTask(new ContextSynchronizerTask($context, $this->year, $this->requestTimeout))
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
        if ($this->oaiUrl) {
            foreach ($contexts->getAll() as $context) {
                $context = (array) $context;
                if ($context['oai_url'] == $this->oaiUrl) {
                    $this->log(print_r($context, 1));
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
