<?php

require_once('vendor/autoload.php');
require_once('classes/Installations.inc.php');
require_once('classes/Contexts.inc.php');
require_once('classes/Beacon.inc.php');

$options = [
	'scriptName' => array_shift($argv),
	'quiet' => false,
	'concurrency' => Beacon::DEFAULT_CONCURRENCY,
	'timeout' => Beacon::DEFAULT_TASK_TIMEOUT,
	'requestTimeout' => Beacon::DEFAULT_REQUEST_TIMEOUT,
	'minimumSecondsBetweenUpdates' => Beacon::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES,
	'oaiUrl' => null,
];
while ($option = array_shift($argv)) switch ($option) {
	case '-q':
	case '--quiet':
		$options['quiet'] = true;
		break;
	case '--oai':
		$options['oaiUrl'] = array_shift($argv);
		if (empty($options['oaiUrl'])) array_unshift($argv, '-h');
		break;
	case '--timeout':
		$options['timeout'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || !$c) array_unshift($argv, '-h');
		break;
	case '--requestTimeout':
		$options['requestTimeout'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || !$c) array_unshift($argv, '-h');
		break;
	case '--minTimeBetween':
		$options['minimumSecondsBetweenUpdates'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || !$c) array_unshift($argv, '-h');
		break;
	case '--concurrency':
		$options['concurrency'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || !$c) array_unshift($argv, '-h');
		break;
	case '-h':
	case '--help':
	default:
		echo "Usage: " . $options['scriptName'] . "
		-h, -usage: Display usage information
		-q, --quiet: Execute quietly (without status display)\n
		--oai http://...: Select the beacon entry with the specified OAI URL to update
		--timeout <n>: Set timeout per task <n> seconds (default " . Beacon::DEFAULT_TASK_TIMEOUT . ")
		--requestTimeout <n>: Set timeout per HTTP request to <n> seconds (default " . Beacon::DEFAULT_REQUEST_TIMEOUT . ")
		--minTimeBetween <n>: Set the minimum time in seconds between updates (default 1 week)
		--concurrency <n>: Set maximum concurrency to <n> simultaneous processes (default " . Beacon::DEFAULT_CONCURRENCY . ")\n";
		exit(-1);
}

if (!$options['quiet']) echo "Queuing processes...\r";

$db = new BeaconDatabase();
$contexts = new Contexts($db);
$pool = Spatie\Async\Pool::create()
	->concurrency($options['concurrency'])
	->timeout($options['timeout'])
	->sleepTime(1000000); // 1 second per tick

$statistics = [
	'selected' => 0, 'skipped' => 0, 'total' => 0
];

foreach ($contexts->getAll() as $context) {
	$context = (array) $context;

	// Select only the desired entries for task queueing.
	$statistics['total']++;
	if (!empty($options['oaiUrl'])) {
		// If a specific OAI URL was specified, update that record.
		if ($entry['oai_url'] != $options['oaiUrl']) {
			$statistics['skipped']++;
			continue;
		}
		else $statistics['selected']++;
	} else {
		// Default: skip anything that was updated successfully in the last week.
		if (time() - strtotime($context['last_completed_update']) < $options['minimumSecondsBetweenUpdates']) {
			$statistics['skipped']++;
			continue;
		}
	}

	$pool->add(function() use ($context, $options) {
		try {
			require('classes/Contexts.inc.php');
			$db = new BeaconDatabase();
			$contexts = new Contexts($db);

			$endpoint = new \Phpoaipmh\Endpoint(new Phpoaipmh\Client($context['oai_url'], new \Phpoaipmh\HttpAdapter\GuzzleAdapter(new \GuzzleHttp\Client([
				'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0'],
				'timeout' => $options['requestTimeout'],
			]))));
			$oaiFailure = false;

			// Use an OAI ListRecords request to get the ISSN.
			if ($context['issn'] === null) try {
				$records = $endpoint->listRecords('oai_dc', null, null, $context['set_spec']);
				$contexts->updateFields($context['id'], ['total_record_count' => $records->getTotalRecordCount()]);
				foreach ($records as $record) {
					if ($record->metadata->getName() === '') continue;
					$metadata = $record->metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/');
					if ($metadata->getName() === '') continue;
					$dc = $metadata->children('http://purl.org/dc/elements/1.1/');
					if ($dc->getName() !== '') foreach ($dc->source as $source) {
						$matches = null;
						if (preg_match('%(\d{4}\-\d{3}(\d|x|X))%', $source, $matches)) {
							$context['issn'] = $matches[1];
							$contexts->updateFields($context['id'], ['issn' => $matches[1]]);
							break 2;
						}
					}
				}
			} catch (Exception $e) {
				$contexts->updateFields($context['id'], [
					'last_error' => 'ListRecords: ' . $e->getMessage(),
					'errors' => ++$context['errors'],
				]);
				$oaiFailure = true;
			}

			// Fetch the country using the ISSN.
			if ($context['issn'] !== null && $context['country'] === null) try {
				$client = new GuzzleHttp\Client();
				$response = $client->request('GET', 'https://portal.issn.org/api/search?search[]=MUST=allissnbis=%22' . $context['issn'] . '%22');
				$matches = null;
				if (preg_match('/<p><span>Country: <\/span>([^<]*)<\/p>/', $response->getBody(), $matches)) {
					$contexts->updateFields($context['id'], ['country' => $matches[1]]);
				}
			} catch (Exception $e) {
				$contexts->updateFields($context['id'], [
					'last_error' => 'Get country: ' . $e->getMessage(),
					'errors' => ++$context['errors'],
				]);
			}

			// Save the updated entry.
			if (!$oaiFailure) $contexts->updateFields($context['id'], [
				'last_completed_update' => $db->formatTime(),
				'last_error' => null
			]);
		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	});
}
if (!$options['quiet']) echo 'Finished queueing. Statistics: ' . print_r($statistics, true) . "Running queue...\n";

while ($pool->getInProgress()) $pool->wait(function($pool) use ($options) {
	if (!$options['quiet']) echo str_replace("\n", "", $pool->status()) . " \r";
});

if (!$options['quiet']) {
	echo "\nFinished!\n";
	if ($options['oaiUrl']) {
		foreach ($contexts->getAll() as $context) {
			$context = (array) $context;
			if ($context['oai_url'] == $options['oaiUrl']) print_r($context);
		}
	}
}
