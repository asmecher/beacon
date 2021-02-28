<?php

declare(strict_types=1);

namespace PKP\Beacon;

class Constants
{
    /** @var string */
    public const USER_AGENT = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0';

    /** @var int */
    public const DEFAULT_CONCURRENCY = 50;

    /** @var int */
    public const DEFAULT_TASK_TIMEOUT = 120;

    /** @var int */
    public const DEFAULT_REQUEST_TIMEOUT = 60;

    /** @var int */
    public const DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES = 60 * 60 * 24 * 7;

    /** @var string */
    public const DEFAULT_TASK_MEMORY_LIMIT = '8M';

    /** @var string */
    public const DEFAULT_MEMORY_LIMIT = '1024M';

    /** @var string */
    public const DEFAULT_MINIMUM_RECORDS = '0';

    /** @var int */
    public const DEFAULT_SCHEDULER_TICK_IN_MICROSECONDS = 1000000;
}
