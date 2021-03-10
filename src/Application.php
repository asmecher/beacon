<?php

declare(strict_types=1);

namespace PKP\Beacon;

class Application
{
    /** @var string Constants for OJS */
    public const OJS = 'OJS';

    /** @var string Constants for OMP */
    public const OMP = 'OMP';

    /** @var string Constants for OPS */
    public const OPS = 'OPS';

    /**
     * Retrieves a list of the applications
     */
    public static function getApplications(): array
    {
        return [
            self::OJS,
            self::OMP,
            self::OJS
        ];
    }
}
