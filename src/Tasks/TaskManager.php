<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;
use Spatie\Async\Pool;
use Spatie\Async\Process\Runnable;

class TaskManager
{
    public $concurrency = Constants::DEFAULT_CONCURRENCY;
    public $memoryLimit = Constants::DEFAULT_MEMORY_LIMIT;
    public $taskMemoryLimit = Constants::DEFAULT_TASK_MEMORY_LIMIT;
    public $timeout = Constants::DEFAULT_TASK_TIMEOUT;
    public $schedulerTick = Constants::DEFAULT_SCHEDULER_TICK_IN_MICROSECONDS;

    protected $pool;
    private $previousMemoryLimit;

    protected function stop(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
    }

    protected function start(): void
    {
        $this->previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', $this->memoryLimit);
    }

    protected function getPool(): Pool
    {
        return $this->pool ?? ($this->pool = Pool::create()
            ->concurrency($this->concurrency)
            ->timeout($this->timeout)
            ->sleepTime($this->schedulerTick));
    }

    public function addTask(BaseTask $task): Runnable
    {
        $task->memoryLimit = $this->taskMemoryLimit;
        return $this->getPool()->add($task);
    }

    public function run(?callable $onTick = null): void
    {
        $this->start();
        try {
            $this->getPool()->wait($onTick);
        } finally {
            $this->stop();
        }
    }
}
