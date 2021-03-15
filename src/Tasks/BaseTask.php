<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;

abstract class BaseTask
{
    /** @var string Default memory limit */
    public $memoryLimit = Constants::DEFAULT_TASK_MEMORY_LIMIT;
    /** @var string Previous memory limit */
    private $previousMemoryLimit;

    /** Method that must be implemented to execute the task */
    abstract public function run(): bool;

    /** Executed the task, this will be called by the Spatie\Async */
    public function __invoke()
    {
        $this->start();
        try {
            return $this->run();
        } finally {
            $this->stop();
        }
    }

    /**
     * Startup step
     */
    protected function start(): void
    {
        $this->previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', $this->memoryLimit);
    }

    /** Cleanup step, only useful if the task is running synchronously */
    protected function stop(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
    }
}
