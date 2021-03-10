<?php

declare(strict_types=1);

namespace PKP\Beacon;

class Constants
{
    /** @var string Default user agent to be used when issuing HTTP requests */
    public const USER_AGENT = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0';

    /** @var int Maximum amount of concurrent tasks */
    public const DEFAULT_CONCURRENCY = 50;

    /** @var int Maximum amount of seconds a task will have to finish its job */
    public const DEFAULT_TASK_TIMEOUT = 120;

    /** @var int Maximum amount of time in seconds to wait for a request response */
    public const DEFAULT_REQUEST_TIMEOUT = 60;

    /** @var int Interval in seconds to consider data as updated, and skip its synchronization */
    public const DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES = 60 * 60 * 24 * 7;

    /** @var string Maximum amount of memory a task can consume */
    public const DEFAULT_TASK_MEMORY_LIMIT = '8M';

    /** @var string Maximum amount of memory a task scheduler can consume */
    public const DEFAULT_MEMORY_LIMIT = '1024M';

    /** @var string Filters out from the export contexts that have less than the given amount of records */
    public const DEFAULT_MINIMUM_RECORDS = '0';

    /** @var int Interval in microseconds to retrieve updates/progress from the task scheduler */
    public const DEFAULT_SCHEDULER_TICK_IN_MICROSECONDS = 1000000;

    /** @var int Default amount of records to retrieve in a paging */
    public const DEFAULT_PAGE_SIZE = 100;
}
