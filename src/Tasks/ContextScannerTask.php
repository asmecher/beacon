<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;
use PKP\Beacon\Database;
use PKP\Beacon\Entities\Contexts;
use PKP\Beacon\Entities\Endpoints;

class ContextScannerTask extends BaseTask
{
    /** @var object The endpoint */
    public $endpoint;
    /** @var int The request timeout */
    public $requestTimeout = Constants::DEFAULT_REQUEST_TIMEOUT;

    public function __construct(object $endpoint, ?int $requestTimeout = null)
    {
        $this->endpoint = $endpoint;
        if ($requestTimeout) {
            $this->requestTimeout = $requestTimeout;
        }
    }

    /** Retrieves the OAI endpoint */
    private function getEndpoint(string $url): \Phpoaipmh\Endpoint
    {
        return new \Phpoaipmh\Endpoint(
            new \Phpoaipmh\Client(
                $url,
                new \Phpoaipmh\HttpAdapter\GuzzleAdapter(
                    new \GuzzleHttp\Client([
                        'headers' => ['User-Agent' => Constants::USER_AGENT],
                        'timeout' => $this->requestTimeout
                    ])
                )
            )
        );
    }

    /** Runs the task */
    public function run(): bool
    {
        try {
            $db = new Database();
            $endpoints = new Endpoints($db);
            $endpoint = $this->endpoint;

            $oaiEndpoint = $this->getEndpoint($endpoint->oai_url);

            // Use an OAI Identify request to get the admin email and test OAI.
            try {
                $result = $oaiEndpoint->identify();
                if (!$result->Identify->baseURL) {
                    return false;
                }
                $endpoints->update($endpoint->id, [
                    'last_oai_response' => $db->formatTime(),
                    'admin_email' => $result->Identify->adminEmail,
                    'earliest_datestamp' => $db->formatTime(strtotime((string) $result->Identify->earliestDatestamp)),
                    'repository_name' => $result->Identify->repositoryName,
                    'admin_email' => $result->Identify->adminEmail,
                ]);
            } catch (\Exception $e) {
                $endpoints->update($endpoint->id, [
                    'last_error' => 'Identify: ' . mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'), // Remove invalid UTF-8, e.g. in the case of a 404
                    'errors' => ++$endpoint->errors,
                ]);
                return false;
            }

            // List sets and populate the context list.
            try {
                $sets = $oaiEndpoint->listSets();
                $contexts = new Contexts($db);
                foreach ($sets as $set) {
                    // Skip "driver" sets (DRIVER plugin for OJS)
                    if ($set->setSpec == 'driver') {
                        continue;
                    }

                    // Skip anything that looks like a journal section
                    if (strpos((string) $set->setSpec, ':') !== false) {
                        continue;
                    }

                    // If we appear to already have this context in the database, skip it.
                    if ($contexts->find(['endpoint_id' => $endpoint->id, 'set_spec' => $set->setSpec])) {
                        continue;
                    }

                    // Add a new context entry to the database.
                    $contexts->insert(['endpoint_id' => $endpoint->id, 'set_spec' => $set->setSpec]);
                }
            } catch (\Exception $e) {
                $endpoints->update($endpoint->id, [
                    'last_error' => 'ListSets: ' . $e->getMessage(),
                    'errors' => ++$endpoint->errors,
                ]);
                return false;
            }

            // Finished; save the updated entry.
            $endpoints->update($endpoint->id, [
                'last_completed_update' => $db->formatTime(),
                'last_error' => null
            ]);

            return true;
        } catch (\Exception $e) {
            // Re-wrap the exception; some types of exceptions can't be instantiated as the async library expects.
            throw new \Exception($e->getMessage());
        }
    }
}
