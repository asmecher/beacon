<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\Command;
use GetOpt\Option;
use PKP\Beacon\Database;

class DatabaseCommand extends BaseCommand
{
    protected function setup(): void
    {
        $command = $this
            ->addOptions(Command::create('database', $this)
            ->setDescription('Operations to manage the database.'));
        $this->options->addCommand($command);
    }

    private function addOptions(Command $command): Command
    {
        return $command->addOptions([
            Option::create('c', 'create')->setDescription('Create the database schema.'),
            Option::create('d', 'drop')->setDescription('Drop the database schema.'),
            Option::create('f', 'flush')->setDescription('Flush database contents.')
        ]);
    }

    public function __invoke(): void
    {
        $options = [
            'create' => function () {
                $this->getDatabase()->createSchema();
                $this->log('Created schema.');
            },
            'drop' => function () {
                $this->getDatabase()->dropSchema();
                $this->log('Dropped schema.');
            },
            'flush' => function () {
                $this->getDatabase()->flush();
                $this->log('Flushed schema.');
            }
        ];
        foreach ($options as $option => $handler) {
            if ($this->options[$option]) {
                $handler();
                return;
            }
        }
        throw new \Exception('Specify an operation.');
    }

    private function getDatabase(): Database
    {
        return new Database();
    }
}
