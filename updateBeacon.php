<?php

require_once('vendor/autoload.php');
require_once('classes/Endpoints.inc.php');
require_once('classes/Contexts.inc.php');
require_once('classes/Beacon.inc.php');
require_once('classes/CountSpans.inc.php');

$options = [
	'scriptName' => array_shift($argv),
	'quiet' => false,
	'concurrency' => Beacon::DEFAULT_CONCURRENCY,
	'timeout' => Beacon::DEFAULT_TASK_TIMEOUT,
	'requestTimeout' => Beacon::DEFAULT_REQUEST_TIMEOUT,
	'processMemoryLimit' => Beacon::DEFAULT_PROCESS_MEMORY_LIMIT,
	'minimumSecondsBetweenUpdates' => Beacon::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES,
	'memoryLimit' => Beacon::DEFAULT_MEMORY_LIMIT,
	'oaiUrl' => null,
	'year' => CountSpans::getDefaultLabel(),
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
	case '--memory_limit':
		$options['memoryLimit'] = $m = array_shift($argv);
		if (empty($m) || !preg_match('/^[0-9]\+M$/', $m)) array_unshift($argv, '-h');
		break;
	case '--process_memory_limit':
		$options['processMemoryLimit'] = $m = array_shift($argv);
		if (empty($m) || !preg_match('/^[0-9]\+M$/', $m)) array_unshift($argv, '-h');
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
		if (!ctype_digit($c) || $c<0) array_unshift($argv, '-h');
		break;
	case '--year':
		$options['year'] = (int) $c = array_shift($argv);
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
	--memory_limit <n>: Set memory limit for parent process to <n> (default " . Beacon::DEFAULT_MEMORY_LIMIT . ")
	--process_memory_limit <n>: Set memory limit per task to <n> (default " . Beacon::DEFAULT_PROCESS_MEMORY_LIMIT . ")
	--timeout <n>: Set timeout per task <n> seconds (default " . Beacon::DEFAULT_TASK_TIMEOUT . ")
	--year <n>: Set year to fetch record span for (default " . CountSpans::getDefaultLabel() . ")
	--requestTimeout <n>: Set timeout per HTTP request to <n> seconds (default " . Beacon::DEFAULT_REQUEST_TIMEOUT . ")
	--minTimeBetween <n>: Set the minimum time in seconds between updates (default 1 week)
	--concurrency <n>: Set maximum concurrency to <n> simultaneous processes (default " . Beacon::DEFAULT_CONCURRENCY . ")\n";
		exit(-1);
}

ini_set('memory_limit', Beacon::DEFAULT_MEMORY_LIMIT);

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

foreach ($contexts->getAll(true) as $context) {
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
		// Default: skip anything that was recently updated successfully.
		if (time() - strtotime($context['last_completed_update']) < $options['minimumSecondsBetweenUpdates']) {
			$statistics['skipped']++;
			continue;
		}
	}

	$pool->add(function() use ($context, $options) {
		ini_set('memory_limit', $options['processMemoryLimit']);
		try {
			require('classes/Contexts.inc.php');
			$db = new BeaconDatabase();
			$contexts = new Contexts($db);

			$oaiEndpoint = new \Phpoaipmh\Endpoint(
				new \Phpoaipmh\Client($context['oai_url'], new \Phpoaipmh\HttpAdapter\GuzzleAdapter(new \GuzzleHttp\Client([
					'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0'],
					'timeout' => $options['requestTimeout'],
				]))),
				\Phpoaipmh\Granularity::DATE
			);
			$oaiFailure = false;

			// Use an OAI ListRecords request to get the ISSN.
			if ($context['issn'] === null) try {
				$records = $oaiEndpoint->listRecords('oai_dc', null, null, $context['set_spec']);
				$contexts->update($context['id'], ['total_record_count' => $records->getTotalRecordCount()]);
				foreach ($records as $record) {
					if ($record->metadata->getName() === '') continue;
					$metadata = $record->metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/');
					if ($metadata->getName() === '') continue;
					$dc = $metadata->children('http://purl.org/dc/elements/1.1/');
					if ($dc->getName() !== '') foreach ($dc->source as $source) {
						$matches = null;
						if (preg_match('%^(\d{4}\-\d{3}(\d|x|X))$%', $source, $matches)) {
							$context['issn'] = $matches[1];
							$contexts->update($context['id'], ['issn' => $matches[1]]);
							break 2;
						}
					}
				}
			} catch (Exception $e) {
				$contexts->update($context['id'], [
					'last_error' => 'ListRecords: ' . $e->getMessage(),
					'errors' => ++$context['errors'],
				]);
				$oaiFailure = true;
			}

			// Fetch the country using the ISSN.
			if ($context['issn'] !== null && $context['country'] === null) try {
				$client = new GuzzleHttp\Client();
				$response = $client->request('GET', 'https://portal.issn.org/resource/ISSN/' . urlencode($context['issn']) . '?format=json');
				$jsonResponse = json_decode($response->getBody(), true);
				if ($jsonResponse && $country = array_reduce($jsonResponse['@graph'], function($carry, $item) {
					return strpos($item['@id'], 'http://id.loc.gov/vocabulary/countries/') !== false ? $item['label'] : $carry;
				})) {
					$contexts->update($context['id'], ['country' => $country]);
				}
			} catch (Exception $e) {
				$contexts->update($context['id'], [
					'last_error' => 'Get country: ' . $e->getMessage(),
					'errors' => ++$context['errors'],
				]);
			}

			require('classes/CountSpans.inc.php');
			$countSpans = new CountSpans($db);
			if (!$countSpans->find(['context_id' => $context['id'], 'label' => $options['year']])) {
				// A count span was not found with the given characteristics. Get one.
				$dateStart = new DateTime($options['year'] . '-01-01');
				$dateEnd = new DateTime($options['year'] . '-12-31');
				try {
					$records = $oaiEndpoint->listRecords('oai_dc', $dateStart, $dateEnd, $context['set_spec']);
					$countSpans->insert([
						'context_id' => $context['id'],
						'label' => $options['year'],
						'record_count' => $records->getTotalRecordCount(),
						'date_start' => $db->formatTime($dateStart->getTimestamp()),
						'date_end' => $db->formatTime($dateEnd->getTimestamp()),
						'date_counted' => $db->formatTime(),
					]);
				} catch (Exception $e) {
					if (strstr($e->getMessage(), 'No matching records in this repository') !== false) {
						$countSpans->insert([
							'context_id' => $context['id'],
							'label' => $options['year'],
							'record_count' => 0,
							'date_start' => $db->formatTime($dateStart->getTimestamp()),
							'date_end' => $db->formatTime($dateEnd->getTimestamp()),
							'date_counted' => $db->formatTime(),
						]);
					} else {
						$contexts->update($context['id'], [
							'last_error' => 'ListRecords for count span "' . $options['year'] . '": ' . $e->getMessage(),
							'errors' => ++$context['errors'],
						]);
						$oaiFailure = true;
					}
				}
			}

			// Save the updated entry.
			if (!$oaiFailure) $contexts->update($context['id'], [
				'last_completed_update' => $db->formatTime(),
				'last_error' => null
			]);

		} catch (Exception $e) {
			throw new Exception($e->getMessage());
		}
	})->catch(function($e) use ($options) {
		// Watch for errors, particularly child process out-of-memory problems.
		if (!$options['quiet']) echo "\r\n(Caught child process exception: " . $e->getMessage() . ")\n";
	});
}
if (!$options['quiet']) echo 'Finished queueing. Statistics: ' . print_r($statistics, true) . "Running queue...\n";

while ($pool->getInProgress()) {
	$pool->wait(function($pool) use ($options) {
		if (!$options['quiet']) echo str_replace("\n", "", $pool->status()) . " \r";
	});
}

if (!$options['quiet']) {
	echo "\nFinished!\n";
	if ($options['oaiUrl']) {
		foreach ($contexts->getAll() as $context) {
			$context = (array) $context;
			if ($context['oai_url'] == $options['oaiUrl']) print_r($context);
		}
	}
}
