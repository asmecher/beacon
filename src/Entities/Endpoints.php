<?php

declare(strict_types=1);

namespace PKP\Beacon\Entities;

use PKP\Beacon\Application;

class Endpoints extends Entity
{
    /** @var array List of invalid OAI patterns */
    public const INVALID_OAI_PATTERNS = [
        '/localhost/', // Localhost
        '/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', // IP addresses
        '/test/', // Test installations
        '/demo[^s]/', // Avoid skipping Greek sociology
        '/theme/', // Themes
        '/\.softaculous\.com/', // Demos
    ];

    /**
     * @copydoc Entity::getTableName()
     */
    protected function getTableName(): string
    {
        return 'endpoints';
    }

    /**
     * Determine a unique string from the specified application and set of query parameters
     */
    public function getDisambiguatorFromQuery(object $query): ?string
    {
        // WARNING: This will undercount endpoints that were duplicated e.g. for splitting journals!
        static $transformations = [
            '/^\w+:\/\//' => '', // Remove protocol
            '/^www\./' => '', // Remove trailing "www"
            '/^/' => '//' // Adds a trailling "//" to the beginning
        ];

        if (!($url = trim($query->oai ?? '')) || !($id = $query->id ?? null)) {
            return null;
        }
        return $id . '-' . preg_replace(array_keys($transformations), array_values($transformations), $url);
    }

    /**
     * Retrieves whether the OAI URL is acceptable.
     */
    public static function isValidOaiUrl(string $url): bool
    {
        foreach (self::INVALID_OAI_PATTERNS as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Given a path, it retrieves to which application it refers
     */
    public static function getApplicationFromPath(string $path): ?string
    {
        static $map;
        if (!$map) {
            foreach (Application::getApplications() as $name) {
                $path = strtolower($name);
                $map['/' . $path . '/xml/' . $path . '-version.xml'] = $name;
            }
        }
        return $map[$path] ?? null;
    }

    /**
     * Get the count of unique endpoints.
     */
    public function getCount(): int
    {
        return $this->db->getCapsule()->table('endpoints')->count();
    }

    /**
     * Get the list of endpoint entries that haven't been updated in a given amount of time.
     *
     * @param int $secondsInterval Amount of time to consider a record as updated
     */
    public function getOutdatedBy(int $secondsInterval, ?int $rows = null): \Generator
    {
        $secondsInterval = (int) $secondsInterval;
        $query = $this->db
            ->getCapsule()
            ->table('endpoints')
            ->where($this->db->raw('COALESCE(TIMESTAMPDIFF(SECOND, last_completed_update, CURRENT_TIMESTAMP), ' . $secondsInterval . ')'), '>=', $secondsInterval);
        return $this->paginateDynamically($query, ['id' => 'id'], $rows);
    }
}
