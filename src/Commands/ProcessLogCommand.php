<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use PKP\Beacon\LogProcessor;

class ProcessLogCommand extends BaseCommand
{
    protected function setup(): void
    {
        $command = $this
            ->addOptions(Command::create('process-log', $this)
            ->setDescription('Processes a log file to discover new endpoints.'));
        $this->options->addCommand($command);
    }

    private function addOptions(Command $command): Command
    {
        return $command->addOptions([
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
        $logProcessor = new LogProcessor();
        $file = new \SplFileObject($this->options['file']);
        try {
            $statistics = $logProcessor->process($file, function ($message) {
                $this->log($message);
            });
            $this->log('Statistics:');
            foreach ($statistics as $key => $value) {
                $this->log("{$key}: {$value}");
            }
        } finally {
            $file = null;
        }
    }
}
