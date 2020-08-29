<?php

require_once('vendor/autoload.php');
require_once('classes/Installations.inc.php');

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
$installations = new Installations($db);

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
		echo $stats['totalBeaconCount'] . ' beacons (' . $installations->getCount() . ' unique) on ' . $stats['totalLogCount'] . " lines...\r";
	}

	parse_str($url['query'] ?? '', $query);
	if (!$disambiguator = $installations->getDisambiguatorFromQuery($query)) {
		// A unique ID could not be determined; count for stats and skip further processing.
		$stats['beaconDisabledLogCount']++;
		continue;
	}

	// Avoid excluded OAI URL forms
	foreach (['/localhost/', '/127\.0\.0\.1/', '/10\/0\/0\.1/'] as $exclusion) {
		if (preg_match($exclusion, $query['oai'])) {
			$stats['excludedCount']++;
			continue 2;
		}
	}

	// Look for an existing installation ID by disambiguator (cached if possible).
	$installationId = null;
	if (isset($disambiguatorIdCache[$disambiguator])) {
		$installationId = $disambiguatorIdCache[$disambiguator];
	} elseif ($installation = $installations->find($disambiguator)) {
		$installationId = $installation['id'];
		$newestDates[$installationId] = strtotime($installation['last_beacon']);
		$disambiguatorIdCache[$disambiguator] = $installationId;
	}

	if (!$installationId) {
		// Create a new beacon entry.
		$installations->addFromQuery($application, $entry->HeaderUserAgent, $query, strtotime($entry->time));
		$stats['newAdditions']++;
	} else {
		// Prepare to store the latest beacon ping date.
		$newestDates[$installationId] = max(strtotime($entry->time), $newestDates[$installationId]);
		$stats['returningBeacons']++;
	}
}
fclose($fp);

if (!$options['quiet']) echo "                                                    \rStoring dates...\r";

// Store the newest ping dates.
foreach ($newestDates as $installationId => $date) {
	$installations->updateFields($installationId, ['last_beacon' => $db->formatTime($date)]);
}


if (!$options['quiet']) {
	echo "                \rStatistics: ";
	print_r($stats);
}

