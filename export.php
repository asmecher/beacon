<?php

require_once('vendor/autoload.php');
require_once('classes/Contexts.inc.php');

$db = new BeaconDatabase();
$contexts = new Contexts($db);
$records = $contexts->getAll();

$fp = fopen('php://stdout', 'w');

// Export column headers
$context = $records->shift();
if (!$context) throw new Exception('No beacon data is currently recorded!');
fputcsv($fp, array_keys((array) $context));

// Export the table contents
do {
	fputcsv($fp, (array) $context);
} while ($context = $records->shift());

fclose($fp);
