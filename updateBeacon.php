<?php

require('vendor/autoload.php');
require('classes/BeaconList.inc.php');

$options = [
	'scriptName' => array_shift($argv),
	'quiet' => false,
	'concurrency' => 50,
	'timeout' => 180,
	'requestTimeout' => 60,
	'minimumSecondsBetweenUpdates' => 60 * 60 * 24 * 7,
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
		--timeout N: Set timeout per entry to N seconds (default 120)
		--requestTimeout N: Set timeout per HTTP request to N seconds (default 60)
		--minTimeBetween N: Set the minimum time in seconds between updates (default 1 week)
		--concurrency N: Set maximum concurrency to N (default 100)\n";
		exit(-1);
}

if (!$options['quiet']) echo "Queuing processes...\r";

$beaconList = new BeaconList();
$pool = Spatie\Async\Pool::create()
	->concurrency($options['concurrency'])
	->timeout($options['timeout'])
	->sleepTime(1000000); // 1 second per tick

$statistics = [
	'selected' => 0, 'skipped' => 0, 'total' => 0
];

foreach ($beaconList->getEntries() as $entry) {
	// Select only the desired entries for task queueing.
	$statistics['total']++;
	if (!empty($options['oaiUrl'])) {
		// If a specific OAI URL was specified, update that record.
		if ($entry['oaiUrl'] != $options['oaiUrl']) {
			$statistics['skipped']++;
			continue;
		}
		else $statistics['selected']++;
	} else {
		// Default: skip anything that was updated successfully in the last week.
		if (time() - strtotime($entry['lastCompletedUpdate']) < $options['minimumSecondsBetweenUpdates']) {
			$statistics['skipped']++;
			continue;
		}
	}

	$pool->add(function () use ($entry, $options) {
		require('classes/BeaconList.inc.php');
		$endpoint = new \Phpoaipmh\Endpoint(new Phpoaipmh\Client($entry['oaiUrl'], new \Phpoaipmh\HttpAdapter\GuzzleAdapter(new \GuzzleHttp\Client([
			'headers' => ['User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:79.0) Gecko/20100101 Firefox/79.0'],
			'timeout' => $options['requestTimeout'],
		]))));
		$oaiFailure = false;

		// Use an OAI Identify request to get the admin email and test OAI.
		try {
			$result = $endpoint->identify();
			if ($result->Identify->baseURL) {
				$entry['lastOaiResponse'] = BeaconList::formatTime();
				$entry['adminEmail'] = $result->Identify->adminEmail;
			}
			else $oaiFailure = true;
		} catch (Exception $e) {
			$entry['lastError'] = 'Identify: ' . $e->getMessage();
			$entry['errors']++;
			$oaiFailure = true;
		}

		// Use an OAI ListRecords request to get the ISSN.
		if (!$oaiFailure && $entry['issn'] === '') try {
			$results = $endpoint->listRecords('oai_dc');
			$entry['totalRecordCount'] = $results->getTotalRecordCount();
			foreach ($results as $result) {
				if ($result->metadata->getName() === '') continue;
				$metadata = $result->metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/');
				if ($metadata->getName() === '') continue;
				$dc = $metadata->children('http://purl.org/dc/elements/1.1/');
				if ($dc->getName() !== '') foreach ($dc->source as $source) {
					$matches = null;
					if (preg_match('%(\d{4}\-\d{3}(\d|x|X))%', $source, $matches)) {
						$entry['issn'] = $matches[1];
						break 2;
					}
				}
			}
		} catch (Exception $e) {
			$entry['lastError'] = 'ListRecords: ' . $e->getMessage();
			$oaiFailure = true;
		}

		// Fetch the country using the ISSN.
		if ($entry['issn'] !== '' && $entry['country'] === '') try {
			$client = new GuzzleHttp\Client();
			$response = $client->request('GET', 'https://portal.issn.org/api/search?search[]=MUST=allissnbis=%22' . $entry['issn'] . '%22');
			$matches = null;
			if (preg_match('/<p><span>Country: <\/span>([^<]*)<\/p>/', $response->getBody(), $matches)) {
			        $entry['country'] = $matches[1];
			}
		} catch (Exception $e) {
			$entry['lastError'] = 'Get country: ' . $e->getMessage();
		}

		// Save the updated entry.
		
		$beaconList = new BeaconList();
		$beaconList->updateFields($entry, $oaiFailure?[]:['lastCompletedUpdate' => $beaconList->formatTime()]);
	});
}
if (!$options['quiet']) echo 'Finished queueing. Statistics: ' . print_r($statistics, true) . "Running queue...\n";

while ($pool->getInProgress()) $pool->wait(function($pool) use ($options) {
	if (!$options['quiet']) echo str_replace("\n", "", $pool->status()) . " \r";
});

if (!$options['quiet']) {
	echo "\nFinished!\n";
	if ($options['oaiUrl']) {
		$beaconList = new BeaconList();
		foreach ($beaconList->getEntries() as $entry) {
			if ($entry['oaiUrl'] == $options['oaiUrl']) print_r($entry);
		}
	}
}
