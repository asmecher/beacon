<?php

declare(strict_types=1);

namespace PKP\Beacon\Commands;

use GetOpt\GetOpt;

abstract class BaseCommand
{
    public $options;

    public function __construct(GetOpt $options)
    {
        $this->options = $options;
        $this->setup();
    }

    abstract protected function setup(): void;

    abstract public function __invoke(): void;

    public function log(string $message): void
    {
        if (!$this->options['quiet']) {
            echo "${message}\n";
        }
    }
}
