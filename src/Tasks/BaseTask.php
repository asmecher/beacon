<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;

abstract class BaseTask
{
    public $memoryLimit = Constants::DEFAULT_TASK_MEMORY_LIMIT;
    private $previousMemoryLimit;

    abstract public function run(): bool;

    public function __invoke()
    {
        $this->start();
        try {
            return $this->run();
        } finally {
            $this->stop();
        }
    }

    protected function start(): void
    {
        $this->previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', $this->memoryLimit);
    }

    protected function stop(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
    }
}
