<?php

require_once('vendor/autoload.php');
require_once('classes/BeaconDatabase.inc.php');

$options = [
	'scriptName' => array_shift($argv),
	'createSchema' => false,
	'dropSchema' => false,
	'flush' => false,
	'quiet' => false,
];
while ($option = array_shift($argv)) switch ($option) {
	case '-c':
	case '--createschema':
		$options['createSchema'] = true;
		break;
	case '-d':
	case '--drop':
		$options['dropSchema'] = true;
		break;
	case '-f':
	case '--flush':
		$options['flush'] = true;
		break;
	case '-q':
	case '--quiet':
		$options['quiet'] = true;
		break;
	case '-h':
	case '--help':
	default:
		echo "Usage: " . $options['scriptName'] . "
	-q, --quiet: Be quiet
	-c, --createschema: Create the database schema
	-d, --drop: Drop the database schema
	-f, --flush: Flush database conents\n";
		exit(-1);
}

if (!$options['flush'] && !$options['createSchema'] && !$options['dropSchema']) throw new Exception('No command specfied. Try the -h option for help.');

$db = new BeaconDatabase();
if ($options['dropSchema']) {
	$db->dropSchema();
	if (!$options['quiet']) echo "Dropped schema.\n";
}
if ($options['createSchema']) {
	$db->createSchema();
	if (!$options['quiet']) echo "Created schema.\n";
}
if ($options['flush']) {
	$db->flush();
	if (!$options['quiet']) echo "Flushed schema.\n";
}

