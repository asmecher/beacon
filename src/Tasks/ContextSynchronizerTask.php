<?php

declare(strict_types=1);

namespace PKP\Beacon\Tasks;

use PKP\Beacon\Constants;
use PKP\Beacon\Database;
use PKP\Beacon\Entities\Contexts;
use PKP\Beacon\Entities\CountSpans;

class ContextSynchronizerTask extends BaseTask
{
    public const ISSN_URL_TEMPLATE = 'https://portal.issn.org/resource/ISSN/%s?format=json';

    public $context;
    public $requestTimeout = Constants::DEFAULT_REQUEST_TIMEOUT;
    public $year;

    public function __construct(array $context, int $year, ?int $requestTimeout = null)
    {
        $this->context = $context;
        $this->year = $year;
        if ($requestTimeout) {
            $this->requestTimeout = $requestTimeout;
        }
    }

    private function getCountryFromIssn(string $issn): ?string
    {
        $client = new \GuzzleHttp\Client();
        $response = json_decode(
            (string) $client->request('GET', sprintf(self::ISSN_URL_TEMPLATE, urlencode($issn)))->getBody(),
            true
        );
        return array_reduce($response['@graph'] ?? [], function ($carry, $item) {
            return strpos($item['@id'], 'http://id.loc.gov/vocabulary/countries/') !== false ? $item['label'] : $carry;
        }) ?: null;
    }

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
            ),
            \Phpoaipmh\Granularity::DATE
        );
    }

    public function run(): bool
    {
        try {
            $db = new Database();
            $contexts = new Contexts($db);

            $oaiEndpoint = $this->getEndpoint($this->context['oai_url']);
            $oaiFailure = false;

            // Use an OAI ListRecords request to get the ISSN.
            if ($this->context['issn'] === null) {
                try {
                    $records = $oaiEndpoint->listRecords('oai_dc', null, null, $this->context['set_spec']);
                    $contexts->update($this->context['id'], ['total_record_count' => $records->getTotalRecordCount()]);
                    foreach ($records as $record) {
                        if ($record->metadata->getName() === '') {
                            continue;
                        }
                        $metadata = $record->metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/');
                        if ($metadata->getName() === '') {
                            continue;
                        }
                        $dc = $metadata->children('http://purl.org/dc/elements/1.1/');
                        if ($dc->getName() !== '') {
                            foreach ($dc->source as $source) {
                                $matches = null;
                                if (preg_match('%^(\d{4}\-\d{3}(\d|x|X))$%', (string) $source, $matches)) {
                                    $this->context['issn'] = $matches[1];
                                    $contexts->update($this->context['id'], ['issn' => $matches[1]]);
                                    break 2;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $contexts->update($this->context['id'], [
                        'last_error' => 'ListRecords: ' . $e->getMessage(),
                        'errors' => ++$this->context['errors'],
                    ]);
                    $oaiFailure = true;
                }
            }

            // Fetch the country using the ISSN.
            if ($this->context['issn'] !== null && $this->context['country'] === null && ($country = $this->getCountryFromIssn($this->context['issn']))) {
                try {
                    $contexts->update($this->context['id'], ['country' => $country]);
                } catch (\Exception $e) {
                    $contexts->update($this->context['id'], [
                        'last_error' => 'Get country: ' . $e->getMessage(),
                        'errors' => ++$this->context['errors']
                    ]);
                }
            }

            $countSpans = new CountSpans($db);
            if (!$countSpans->find(['context_id' => $this->context['id'], 'label' => $this->year])) {
                // A count span was not found with the given characteristics. Get one.
                $dateStart = new \DateTime($this->year . '-01-01');
                $dateEnd = new \DateTime($this->year . '-12-31');
                try {
                    $records = $oaiEndpoint->listRecords('oai_dc', $dateStart, $dateEnd, $this->context['set_spec']);
                    $countSpans->insert([
                        'context_id' => $this->context['id'],
                        'label' => $this->year,
                        'record_count' => $records->getTotalRecordCount(),
                        'date_start' => $db->formatTime($dateStart->getTimestamp()),
                        'date_end' => $db->formatTime($dateEnd->getTimestamp()),
                        'date_counted' => $db->formatTime(),
                    ]);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'No matching records in this repository') !== false) {
                        $countSpans->insert([
                            'context_id' => $this->context['id'],
                            'label' => $this->year,
                            'record_count' => 0,
                            'date_start' => $db->formatTime($dateStart->getTimestamp()),
                            'date_end' => $db->formatTime($dateEnd->getTimestamp()),
                            'date_counted' => $db->formatTime(),
                        ]);
                    } else {
                        $contexts->update($this->context['id'], [
                            'last_error' => 'ListRecords for count span "' . $this->year . '": ' . $e->getMessage(),
                            'errors' => ++$this->context['errors'],
                        ]);
                        $oaiFailure = true;
                    }
                }
            }

            // Save the updated entry.
            if (!$oaiFailure) {
                $contexts->update($this->context['id'], [
                    'last_completed_update' => $db->formatTime(),
                    'last_error' => null
                ]);
            }
            return !$oaiFailure;
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
