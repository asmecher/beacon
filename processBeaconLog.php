<?php

require_once('vendor/autoload.php');
require_once('classes/Endpoints.inc.php');

const pathToApplicationMap = [
	'/ojs/xml/ojs-version.xml' => 'ojs',
	'/omp/xml/omp-version.xml' => 'omp',
	'/ops/xml/ops-version.xml' => 'ops',
];

$options = [
	'scriptName' => array_shift($argv),
	'quiet' => false,
	'inputFile' => 'php://stdin',
];
while ($option = array_shift($argv)) switch ($option) {
	case '-q':
	case '--quiet':
		$options['quiet'] = true;
		break;
	case '-f':
		$options['inputFile'] = $c = array_shift($options);
		if (empty($c) || !file_exists($c)) array_unshift($options, '-h');
		if (!is_readable($c)) throw new Exception('Cannot read input file "' . $c . '"!');
		break;
	case '-h':
	case '--help':
	default:
		echo "Usage: " . $options['scriptName'] . "
		-h, -usage: Display usage information
		-f <filename>: Read log entries from the specified filename
		-q, --quiet: Execute quietly (without status display)\n";
		exit(-1);
}

$db = new BeaconDatabase();
$endpoints = new Endpoints($db);

$stats = [
	'ojsLogCount' => 0, 'ompLogCount' => 0, 'opsLogCount' => 0,
	'beaconDisabledLogCount' => 0, 'excludedCount' => 0,
	'totalBeaconCount' => 0, 'totalLogCount' => 0,
	'newAdditions' => 0, 'returningBeacons' => 0,
];
$parser = new \Kassner\LogParser\LogParser();
$parser->setFormat('%h %l %u %t "%r" %>s %b "%{Referer}i" \"%{User-Agent}i"');
$fp = fopen($options['inputFile'], 'r');
if (!$fp) throw new Exception('Could not open input file!');

// This is used to cache mappings from disambiguators to IDs.
$disambiguatorIdCache = [];
// This is used to store latest-encountered dates, batching updates at the end for performance.
$newestDates = [];

while ($line = fgets($fp)) {
	$stats['totalLogCount']++;

	// Optimization: If this substring isn't present, skip the entry immediately.
	if (!strstr($line, 'version.xml')) continue;

	$entry = $parser->parse($line);

	$matches = null;
	if (!preg_match('/^GET (.*) HTTP\/1\.[01]$/', $entry->request, $matches)) continue; // Not a beacon
	if (!($url = parse_url($matches[1])) || !isset($url['path'])) continue; // Not a beacon

	$application = pathToApplicationMap[$url['path']] ?? null;
	if (!$application) continue; // It's not a beacon log entry; continue to next line
	$stats[$application . 'LogCount']++;
	$stats['totalBeaconCount']++;
	if (!$options['quiet'] && $stats['totalBeaconCount']%100 == 0) {
		echo $stats['totalBeaconCount'] . ' beacons (' . $endpoints->getCount() . ' unique) on ' . $stats['totalLogCount'] . " lines...\r";
	}

	parse_str($url['query'] ?? '', $query);
	if (!$disambiguator = $endpoints->getDisambiguatorFromQuery($query)) {
		// A unique ID could not be determined; count for stats and skip further processing.
		$stats['beaconDisabledLogCount']++;
		continue;
	}

	// Avoid excluded OAI URL forms
	foreach ([
		'/localhost/',
		'/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/', // IP addresses
		'/test/',
		'/demo[^s]/', // Avoid skipping Greek sociology
		'/theme/',
		'/\.softaculous\.com/', // Demos
	] as $exclusion) {
		if (preg_match($exclusion, $query['oai'])) {
			$stats['excludedCount']++;
			continue 2;
		}
	}

	// Look for an existing endpoint ID by disambiguator (cached if possible).
	$endpointId = null;
	if (isset($disambiguatorIdCache[$disambiguator])) {
		$endpointId = $disambiguatorIdCache[$disambiguator];
	} elseif ($endpoint = $endpoints->find($disambiguator)) {
		$endpointId = $endpoint['id'];
		$newestDates[$endpointId] = strtotime($endpoint['last_beacon']);
		$disambiguatorIdCache[$disambiguator] = $endpointId;
	}

	if (!$endpointId) {
		// Create a new beacon entry.
		$endpoints->addFromQuery($application, $entry->HeaderUserAgent, $query, strtotime($entry->time));
		$stats['newAdditions']++;
	} else {
		// Prepare to store the latest beacon ping date.
		$newestDates[$endpointId] = max(strtotime($entry->time), $newestDates[$endpointId]);
		$stats['returningBeacons']++;
	}
}
fclose($fp);

if (!$options['quiet']) echo "                                                    \rStoring dates...\r";

// Store the newest ping dates.
foreach ($newestDates as $endpointId => $date) {
	$endpoints->updateFields($endpointId, ['last_beacon' => $db->formatTime($date)]);
}


if (!$options['quiet']) {
	echo "                \rStatistics: ";
	print_r($stats);
}

