<?php

declare(strict_types=1);

namespace PKP\Beacon;

use PKP\Beacon\Entities\Contexts;
use PKP\Beacon\Tasks\ContextSynchronizerTask;
use PKP\Beacon\Tasks\TaskManager;

class ContextSynchronizer extends TaskManager
{
    /** @var callable|null Logger function, receives a string */
    public $logger;
    /** @var int Interval of seconds to consider a context as updated */
    public $minimumSecondsBetweenUpdates = Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES;
    /** @var int|null Maximum amount of time to wait for a response */
    public $requestTimeout;
    /** @var string|null Filters out contexts that are not part of the given OAI URL */
    public $oaiUrl;
    /** @var int|null Extracts only information for the given year */
    public $year;
    /** @var \Generator Source of contexts */
    private $generator;

    /**
     * Lazily creates and retrieves a generator
     */
    private function getGenerator(): \Generator
    {
        if (!$this->generator) {
            $db = new Database();
            $contexts = new Contexts($db);
            $this->generator = $this->oaiUrl
                ? $contexts->getByOaiUrl($this->oaiUrl)
                : $contexts->getOutdatedBy($this->minimumSecondsBetweenUpdates, $this->concurrency * 2);
        }
        return $this->generator;
    }

    /**
     * Retrieves whether there's more data to be consumed
     */
    private function hasData(): bool
    {
        return $this->getGenerator()->valid();
    }

    /**
     * Retrieves an item from the generator and advances it
     */
    private function getNext(): ?object
    {
        $generator = $this->getGenerator();
        $current = $generator->current();
        $generator->next();
        return $current;
    }

    /**
     * Fills the task scheduler with data until it's full and outputs updates
     */
    private function fillQueue(): void
    {
        $this->log($this->getStatus(), true);
        while ($this->hasQueueSlot() && $this->hasData()) {
            $this
                ->addTask(new ContextSynchronizerTask($this->getNext(), $this->year, $this->requestTimeout))
                ->catch(function (\Exception $e) {
                    // Watch for errors, particularly child process out-of-memory problems.
                    $this->log('Caught task exception: ' . $e->getMessage());
                });
            $this->log($this->getStatus(), true);
        }
    }

    /**
     * Starts the processing
     */
    public function process(): void
    {
        $this->log('Queuing and running tasks...');
        // Just in case the task finish before the "tick" happens
        while ($this->hasData()) {
            // Fill in initial queue
            $this->fillQueue();
            // And keep filling in when the task scheduler ticks
            $this->getPool()->wait(function () {
                $this->fillQueue();
            });
        }
        $this->log($this->getStatus());
        $this->log('Context synchronization completed!');
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
