<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use PKP\Beacon\Constants;
use PKP\Beacon\ContextScanner;

class ScanContextsCommand extends BaseCommand
{
    protected function setup(): void
    {
        $command = $this
            ->addOptions(Command::create('scan', $this)
            ->setDescription('Scans the discovered endpoints for contexts.'));
        $this->options->addCommand($command);
    }

    private function addOptions(Command $command): Command
    {
        return $command->addOptions([
            Option::create(null, 'concurrency')
                ->setDescription('Set maximum concurrency to <n> simultaneous processes (default ' . Constants::DEFAULT_CONCURRENCY . ')')
                ->setDefaultValue(Constants::DEFAULT_CONCURRENCY)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create(null, 'timeout')
                ->setDescription('Set timeout per task <n> seconds (default ' . Constants::DEFAULT_TASK_TIMEOUT . ')')
                ->setDefaultValue(Constants::DEFAULT_TASK_TIMEOUT)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create(null, 'requestTimeout')
                ->setDescription('Set timeout per HTTP request to <n> seconds (default ' . Constants::DEFAULT_REQUEST_TIMEOUT . ')')
                ->setDefaultValue(Constants::DEFAULT_REQUEST_TIMEOUT)
                ->setValidation(function ($value) {
                }, 'Invalid value'),
            Option::create(null, 'minimumSecondsBetweenUpdates')
                ->setDescription('Set the minimum time in seconds between updates (default ' . Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES . ' seconds)')
                ->setDefaultValue(Constants::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create(null, 'memoryLimit')
                ->setDescription('Set memory limit for parent process to the given value (default ' . Constants::DEFAULT_MEMORY_LIMIT . ')')
                ->setDefaultValue(Constants::DEFAULT_MEMORY_LIMIT)
                ->setValidation(function ($value) {
                    return !empty($value) && preg_match('/^[0-9]+M$/', $value);
                }, 'Invalid value'),
            Option::create(null, 'oai')
                ->setDescription('Select the endpoint(s) with the specified OAI URL to update')
                ->setValidation(function ($value) {
                    return !empty($value);
                }, 'Invalid value'),
            Option::create('f', 'file', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Read log entries from the specified filename (default: stdin)')
                ->setDefaultValue('php://stdin')
                ->setValidation(function ($value) {
                    return (new \SplFileInfo($value))->isReadable();
                }, 'The input file must exist and be readable.')
        ]);
    }

    public function __invoke(): void
    {
        $contextScanner = new ContextScanner(function ($message) {
            $this->log($message);
        });
        foreach (['concurrency', 'timeout', 'requestTimeout', 'minimumSecondsBetweenUpdates', 'memoryLimit', 'oaiUrl'] as $setting) {
            $contextScanner->{$setting} = $this->options[$setting];
        }
        $contextScanner->process();
    }
}
