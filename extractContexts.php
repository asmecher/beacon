<?php

require_once('vendor/autoload.php');
require_once('classes/Endpoints.inc.php');
require_once('classes/Beacon.inc.php');

$options = [
	'scriptName' => array_shift($argv),
	'quiet' => false,
	'concurrency' => Beacon::DEFAULT_CONCURRENCY,
	'timeout' => Beacon::DEFAULT_TASK_TIMEOUT,
	'requestTimeout' => Beacon::DEFAULT_REQUEST_TIMEOUT,
	'minimumSecondsBetweenUpdates' => Beacon::DEFAULT_MINIMUM_SECONDS_BETWEEN_UPDATES,
	'memoryLimit' => Beacon::DEFAULT_MEMORY_LIMIT,
	'oaiUrl' => null,
];
while ($option = array_shift($argv)) switch ($option) {
	case '-q':
	case '--quiet':
		$options['quiet'] = true;
		break;
	case '--memory_limit':
		$options['memoryLimit'] = $m = array_shift($argv);
		if (empty($m) || !preg_match('/^[0-9]\+M$/', $m)) array_unshift($argv, '-h');
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
	--memory_limit <n>: Set memory limit for parent process to <n> (default " . Beacon::DEFAULT_MEMORY_LIMIT . ")
	--oai http://...: Select the endpoint(s) with the specified OAI URL to update
	--timeout <n>: Set timeout per task <n> seconds (default " . Beacon::DEFAULT_TASK_TIMEOUT . ")
	--requestTimeout <n>: Set timeout per HTTP request to <n> seconds (default " . Beacon::DEFAULT_REQUEST_TIMEOUT . ")
	--minTimeBetween <n>: Set the minimum time in seconds between updates (default 1 week)
	--concurrency <n>: Set maximum concurrency to <n> simultaneous processes (default " . Beacon::DEFAULT_CONCURRENCY . ")\n";
		exit(-1);
}

ini_set('memory_limit', $options['memoryLimit']);

if (!$options['quiet']) echo "Queuing processes...\r";

$db = new BeaconDatabase();
$endpoints = new Endpoints($db);
$pool = Spatie\Async\Pool::create()
	->concurrency($options['concurrency'])
	->timeout($options['timeout'])
	->sleepTime(1000000); // 1 second per tick

$statistics = [
	'selected' => 0, 'skipped' => 0, 'total' => 0
];

foreach ($endpoints->getAll() as $endpoint) {
	$endpoint = (array) $endpoint;

	// Select only the desired endpoint for task queueing.
	$statistics['total']++;
	if (!empty($options['oaiUrl'])) {
		// If a specific OAI URL was specified, update that record.
		if ($endpoint['oai_url'] != $options['oaiUrl']) {
			$statistics['skipped']++;
			continue;
		}
		else $statistics['selected']++;
	} else {
		// Default: skip anything that was updated successfully in the last week.
		if (time() - strtotime($endpoint['last_completed_update']) < $options['minimumSecondsBetweenUpdates']) {
			$statistics['skipped']++;
			continue;
		}
	}

	$pool->add(function() use ($endpoint, $options) {
		try {
			require_once('classes/Endpoints.inc.php');
			require_once('classes/Contexts.inc.php');
			$db = new BeaconDatabase();
			$endpoints = new Endpoints($db);

			$oaiEndpoint = new \Phpoaipmh\Endpoint(new Phpoaipmh\Client($endpoint['oai_url'], new \Phpoaipmh\HttpAdapter\GuzzleAdapter(new \GuzzleHttp\Client([
				'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0'],
				'timeout' => $options['requestTimeout'],
			]))));
			$oaiFailure = false;

			// Use an OAI Identify request to get the admin email and test OAI.
			try {
				$result = $oaiEndpoint->identify();
				if ($result->Identify->baseURL) {
					$endpoints->update($endpoint['id'], [
						'last_oai_response' => $db->formatTime(),
						'admin_email' => $result->Identify->adminEmail,
						'earliest_datestamp' => $db->formatTime(strtotime($result->Identify->earliestDatestamp)),
						'repository_name' => $result->Identify->repositoryName,
						'admin_email' => $result->Identify->adminEmail,
					]);
				}
				else $oaiFailure = true;
			} catch (Exception $e) {
				$endpoints->update($endpoint['id'], [
					'last_error' => 'Identify: ' . mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8'), // Remove invalid UTF-8, e.g. in the case of a 404
					'errors' => ++$endpoint['errors'],
				]);
				$oaiFailure = true;
			}

			// List sets and populate the context list.
			if (!$oaiFailure) try {
				$sets = $oaiEndpoint->listSets();
				$contexts = new Contexts($db);
				foreach ($sets as $set) {
					// Skip "driver" sets (DRIVER plugin for OJS)
					if ($set->setSpec == 'driver') continue;

					// Skip anything that looks like a journal section
					if (strstr($set->setSpec, ':') !== false) continue;

					// If we appear to already have this context in the database, skip it.
					if ($contexts->find(['endpoint_id' => $endpoint['id'], 'set_spec' => $set->setSpec])) continue;

					// Add a new context entry to the database.
					$contexts->insert(['endpoint_id' => $endpoint['id'], 'set_spec' => $set->setSpec]);
				}
			} catch (Exception $e) {
				$endpoints->update($endpoint['id'], [
					'last_error' => 'ListSets: ' . $e->getMessage(),
					'errors' => ++$endpoint['errors'],
				]);
				$oaiFailure = true;
			}

			// Finished; save the updated entry.
			if (!$oaiFailure) $endpoints->update($endpoint['id'], [
				'last_completed_update' => $db->formatTime(),
				'last_error' => null
			]);
		} catch (Exception $e) {
			// Re-wrap the exception; some types of exceptions can't be instantiated as the async library expects.
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
		$endpoints = new Endpoints($db);
		foreach ($endpoints->getAll() as $endpoint) {
			$endpoint = (array) $endpoint;
			if ($endpoint['oai_url'] == $options['oaiUrl']) print_r($endpoint);
		}
	}
}
