<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use PKP\Beacon\Constants;
use PKP\Beacon\ContextSynchronizer;
use PKP\Beacon\Entities\CountSpans;

class ContextSynchronizerCommand extends BaseCommand
{
    /** Setups the command */
    protected function setup(): void
    {
        $command = $this
            ->addOptions(Command::create('synchronize', $this)
            ->setDescription('Harvest the discovered contexts.'));
        $this->options->addCommand($command);
    }

    /** Adds the options */
    private function addOptions(Command $command): Command
    {
        return $command->addOptions([
            Option::create('c', 'concurrency', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set maximum concurrency to <n> simultaneous processes (default ' . Constants::DEFAULT_CONCURRENCY . ')')
                ->setDefaultValue(Constants::DEFAULT_CONCURRENCY)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create('t', 'timeout', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set timeout per task <n> seconds (default ' . Constants::DEFAULT_TASK_TIMEOUT . ')')
                ->setDefaultValue(Constants::DEFAULT_TASK_TIMEOUT)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create('r', 'requestTimeout', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set timeout per HTTP request to <n> seconds (default ' . Constants::DEFAULT_REQUEST_TIMEOUT . ')')
                ->setDefaultValue(Constants::DEFAULT_REQUEST_TIMEOUT)
                ->setValidation(function ($value) {
                }, 'Invalid value'),
            Option::create('m', 'minimumSecondsBetweenUpdates', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set the minimum time in seconds between updates (default ' . (Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES) . ' seconds)')
                ->setDefaultValue(Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create('L', 'memoryLimit', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set memory limit for parent process to the given value (default ' . Constants::DEFAULT_MEMORY_LIMIT . ')')
                ->setDefaultValue(Constants::DEFAULT_MEMORY_LIMIT)
                ->setValidation(function ($value) {
                    return !empty($value) && preg_match('/^[0-9]+M$/', $value);
                }, 'Invalid value'),
            Option::create('l', 'taskMemoryLimit', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set memory limit per task to the given value (default ' . Constants::DEFAULT_TASK_MEMORY_LIMIT . ')')
                ->setDefaultValue(Constants::DEFAULT_TASK_MEMORY_LIMIT)
                ->setValidation(function ($value) {
                    return !empty($value) && preg_match('/^[0-9]+M$/', $value);
                }, 'Invalid value'),
            Option::create('o', 'oaiUrl', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Select the endpoint(s) with the specified OAI URL to update')
                ->setValidation(function ($value) {
                    return !empty($value);
                }, 'Invalid value'),
            Option::create('y', 'year', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set year to fetch record span for (default ' . CountSpans::getDefaultLabel() . ')')
                ->setDefaultValue(CountSpans::getDefaultLabel())
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value')
        ]);
    }

    /** Executes the command */
    public function __invoke(): void
    {
        $contextSynchronizer = new ContextSynchronizer();
        $contextSynchronizer->logger = function ($message) {
            $this->log($message);
        };
        foreach (['concurrency', 'timeout', 'requestTimeout', 'minimumSecondsBetweenUpdates', 'memoryLimit', 'taskMemoryLimit', 'oaiUrl', 'year'] as $setting) {
            $contextSynchronizer->{$setting} = $this->options[$setting];
        }
        $contextSynchronizer->process();
    }
}
