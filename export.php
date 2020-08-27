<?php

require('vendor/autoload.php');

require_once('classes/BeaconList.inc.php');
$beaconList = new BeaconList();
$entries = $beaconList->getEntries();
$fp = fopen('php://stdout', 'w');

// Export column headers
$entry = $entries->shift();
if (!$entry) throw new Exception('No entries are currenty recorded!');
fputcsv($fp, array_keys((array) $entry));

// Export the table contents
do {
	fputcsv($fp, (array) $entry);
} while ($entry = $entries->shift());
fclose($fp);
