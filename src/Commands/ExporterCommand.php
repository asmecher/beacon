<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\Command;
use GetOpt\GetOpt;
use GetOpt\Option;
use PKP\Beacon\Constants;
use PKP\Beacon\Entities\CountSpans;
use PKP\Beacon\Exporter;

class ExporterCommand extends BaseCommand
{
    /** Setups the command */
    protected function setup(): void
    {
        $command = $this
            ->addOptions(Command::create('export', $this)
            ->setDescription('Exports the beacon data as CSV.'));
        $this->options->addCommand($command);
    }

    /** Adds the options */
    private function addOptions(Command $command): Command
    {
        return $command->addOptions([
            Option::create('y', 'year', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set year to fetch record span for (default ' . CountSpans::getDefaultLabel() . ')')
                ->setDefaultValue(CountSpans::getDefaultLabel())
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value;
                }, 'Invalid value'),
            Option::create('m', 'minRecords', GetOpt::OPTIONAL_ARGUMENT)
                ->setDescription('Set the minimum number of records to include for the specified year (default ' . Constants::DEFAULT_MINIMUM_RECORDS . ')')
                ->setDefaultValue(Constants::DEFAULT_MINIMUM_RECORDS)
                ->setValidation(function ($value) {
                    return ctype_digit($value) && $value >= 0;
                }, 'Invalid value'),
            Option::create('f', 'file', GetOpt::REQUIRED_ARGUMENT)
                ->setDescription('Set the output filename (default stdout)')
                ->setDefaultValue('php://stdout')
                ->setValidation(function ($value) {
                    return (new \SplFileInfo($value))->isReadable();
                }, 'The input file must exist and be readable.')
        ]);
    }

    /** Executes the command */
    public function __invoke(): void
    {
        $file = new \SplFileObject($this->options['file'], 'w');
        try {
            $exporter = new Exporter($file, (int) $this->options['year'], (int) $this->options['minRecords']);
            $exporter->process();
        } finally {
            $file = null;
        }
    }
}
