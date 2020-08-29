<?php

require_once('vendor/autoload.php');
require_once('classes/Installations.inc.php');
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
		--oai http://...: Select the installation with the specified OAI URL to update
		--timeout <n>: Set timeout per task <n> seconds (default " . Beacon::DEFAULT_TASK_TIMEOUT . ")
		--requestTimeout <n>: Set timeout per HTTP request to <n> seconds (default " . Beacon::DEFAULT_REQUEST_TIMEOUT . ")
		--minTimeBetween <n>: Set the minimum time in seconds between updates (default 1 week)
		--concurrency <n>: Set maximum concurrency to <n> simultaneous processes (default " . Beacon::DEFAULT_CONCURRENCY . ")\n";
		exit(-1);
}

if (!$options['quiet']) echo "Queuing processes...\r";

$db = new BeaconDatabase();
$installations = new Installations($db);
$pool = Spatie\Async\Pool::create()
	->concurrency($options['concurrency'])
	->timeout($options['timeout'])
	->sleepTime(1000000); // 1 second per tick

$statistics = [
	'selected' => 0, 'skipped' => 0, 'total' => 0
];

foreach ($installations->getAll() as $installation) {
	$installation = (array) $installation;

	// Select only the desired installations for task queueing.
	$statistics['total']++;
	if (!empty($options['oaiUrl'])) {
		// If a specific OAI URL was specified, update that record.
		if ($installation['oai_url'] != $options['oaiUrl']) {
			$statistics['skipped']++;
			continue;
		}
		else $statistics['selected']++;
	} else {
		// Default: skip anything that was updated successfully in the last week.
		if (time() - strtotime($installation['last_completed_update']) < $options['minimumSecondsBetweenUpdates']) {
			$statistics['skipped']++;
			continue;
		}
	}

	$pool->add(function() use ($installation, $options) {
		try {
			require_once('classes/Installations.inc.php');
			require_once('classes/Contexts.inc.php');
			$db = new BeaconDatabase();
			$installations = new Installations($db);
			$disambiguator = $installation['disambiguator'];

			$endpoint = new \Phpoaipmh\Endpoint(new Phpoaipmh\Client($installation['oai_url'], new \Phpoaipmh\HttpAdapter\GuzzleAdapter(new \GuzzleHttp\Client([
				'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0'],
				'timeout' => $options['requestTimeout'],
			]))));
			$oaiFailure = false;

			// Use an OAI Identify request to get the admin email and test OAI.
			try {
				$result = $endpoint->identify();
				if ($result->Identify->baseURL) {
					$installations->updateFields($installation['id'], [
						'last_oai_response' => $db->formatTime(),
						'admin_email' => $result->Identify->adminEmail
					]);
				}
				else $oaiFailure = true;
			} catch (Exception $e) {
				$installations->updateFields($installation['id'], [
					'last_error' => 'Identify: ' . $e->getMessage(),
					'errors' => ++$installation['errors'],
				]);
				$oaiFailure = true;
			}

			// List sets and populate the context list.
			if (!$oaiFailure) try {
				$sets = $endpoint->listSets();
				$contexts = new Contexts($db);
				$contexts->flushByInstallationId($installation['id']);
				foreach ($sets as $set) {
					// Skip anything that looks like a journal section
					if (strstr($set->setSpec, ':') !== false) continue;

					$contexts->add($installation['id'], $set->setSpec);
				}
			} catch (Exception $e) {
				$installations->updateFields($installation['id'], [
					'last_error' => 'ListSets: ' . $e->getMessage(),
					'errors' => ++$installation['errors'],
				]);
				$oaiFailure = true;
			}

			// Finished; save the updated entry.
			if (!$oaiFailure) $installations->updateFields($installation['id'], [
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
		$installations = new Installations($db);
		foreach ($installations->getAll() as $installation) {
			$installation = (array) $installation;
			if ($installation['oai_url'] == $options['oaiUrl']) print_r($installation);
		}
	}
}
