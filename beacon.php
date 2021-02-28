<?php

declare(strict_types=1);

namespace PKP\Beacon;

require_once __DIR__ . '/vendor/autoload.php';

use GetOpt\GetOpt;
use GetOpt\Option;

$cli = new GetOpt([
    Option::create('q', 'quiet')->setDescription('Execute quietly (without status display)'),
    Option::create('h', 'help')->setDescription('Display usage information')
], [GetOpt::SETTING_STRICT_OPERANDS => true]);

$commands = [
    Commands\DatabaseCommand::class,
    Commands\ProcessLogCommand::class,
    Commands\ScanContextsCommand::class,
    Commands\SynchronizeContextsCommand::class,
    Commands\ExportCommand::class
];
foreach ($commands as $command) {
    new $command($cli);
}

try {
    $cli->process();
    if ($cli['help']) {
        echo $cli->getHelpText();
        exit(0);
    }
    /** @var Command */
    if (!($command = $cli->getCommand())) {
        throw new \Exception('Command is required');
    }
    $command->getHandler()();
} catch (\Exception $exception) {
    if (!$cli['quiet']) {
        echo 'Error: ', $exception->getMessage(), "\n";
        echo $cli->getHelpText();
    }
    exit(-1);
}
