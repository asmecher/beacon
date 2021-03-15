<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\GetOpt;

abstract class BaseCommand
{
    /** @var GetOpt The GetOpt instance */
    public $options;

    public function __construct(GetOpt $options)
    {
        $this->options = $options;
        $this->setup();
    }

    /** Setups the command */
    abstract protected function setup(): void;

    /** Executes the command */
    abstract public function __invoke(): void;

    /** Logs a message to the stdout if the quiet option isn't set */
    public function log(string $message): void
    {
        if (!$this->options['quiet']) {
            echo $message;
        }
    }
}
