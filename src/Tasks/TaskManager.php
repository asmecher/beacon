<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;
use Spatie\Async\Pool;
use Spatie\Async\Process\Runnable;

class TaskManager
{
    /** @var int View the constant documentation */
    public $concurrency = Constants::DEFAULT_CONCURRENCY;
    /** @var string View the constant documentation */
    public $memoryLimit = Constants::DEFAULT_MEMORY_LIMIT;
    /** @var string View the constant documentation */
    public $taskMemoryLimit = Constants::DEFAULT_TASK_MEMORY_LIMIT;
    /** @var int View the constant documentation */
    public $timeout = Constants::DEFAULT_TASK_TIMEOUT;
    /** @var int View the constant documentation */
    public $schedulerTick = Constants::DEFAULT_SCHEDULER_TICK_IN_MICROSECONDS;

    /** @var Pool The pool of tasks */
    protected $pool;
    /** @var string The previous memory limit */
    private $previousMemoryLimit;

    public function __construct()
    {
        $this->setMemoryLimit();
    }

    public function __destruct()
    {
        $this->restoreMemoryLimit();
    }

    /** Restore the previous memory limit */
    protected function restoreMemoryLimit(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
    }

    /** Setup the memory limit */
    protected function setMemoryLimit(): void
    {
        $this->previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', $this->memoryLimit);
    }

    /** Lazily creates and retrieves a pool */
    protected function getPool(): Pool
    {
        return $this->pool ?? ($this->pool = Pool::create()
            ->concurrency($this->concurrency)
            ->timeout($this->timeout)
            ->sleepTime($this->schedulerTick));
    }

    /** Adds a task and sets its memory limit */
    public function addTask(BaseTask $task): Runnable
    {
        $task->memoryLimit = $this->taskMemoryLimit;
        return $this->getPool()->add($task);
    }

    /** Retrieves whether there's available slots in the queue (we're considering the queue has the same size as the concurrency) */
    public function hasQueueSlot(): bool
    {
        return count($this->getPool()->getQueue()) < $this->concurrency;
    }

    /** Retrieves a helpful textual progress */
    public function getStatus(): string
    {
        $pool = $this->getPool();
        return 'Tasks: ' . str_replace("\n", '', 'running: ' . count($pool->getInProgress()) . ' - ' . $pool->status());
    }
}
