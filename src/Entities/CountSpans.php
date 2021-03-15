<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

class CountSpans extends Entity
{
    /**
     * @copydoc Entity::getTableName()
     */
    protected function getTableName(): string
    {
        return 'count_spans';
    }

    public static function getDefaultLabel(): int
    {
        $currentYear = date('Y');
        $currentMonth = date('n');
        return $currentMonth >= 6 ? $currentYear - 1 : $currentYear - 2;
    }
}
