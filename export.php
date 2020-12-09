<?php

require_once('vendor/autoload.php');
require_once('classes/Beacon.inc.php');
require_once('classes/Contexts.inc.php');
require_once('classes/CountSpans.inc.php');

use Illuminate\Database\Capsule\Manager as Capsule;

$options = [
	'scriptName' => array_shift($argv),
	'year' => CountSpans::getDefaultLabel(),
	'minRecords' => Beacon::DEFAULT_MINIMUM_RECORDS,
];

while ($option = array_shift($argv)) switch ($option) {
	case '--year':
		$options['year'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || !$c) array_unshift($argv, '-h');
		break;
	case '--min_records':
		$options['minRecords'] = (int) $c = array_shift($argv);
		if (!ctype_digit($c) || $c<0) array_unshift($argv, '-h');
		break;
	case '-h':
	case '--help':
	default:
		echo "Usage: " . $options['scriptName'] . "
	--min_records <n>: Set the minimum number of records to include for the specified year (default " . Beacon::DEFAULT_MINIMUM_RECORDS . ")
	--year <n>: Set year to fetch record span for (default " . CountSpans::getDefaultLabel() . ")\n";
		exit(-1);
}
$db = new BeaconDatabase();
$capsule = $db->getCapsule();
$query = $capsule->table('contexts')
	->join('endpoints', 'contexts.endpoint_id', '=', 'endpoints.id')
	->leftJoin('count_spans', function($join) use ($options) {
		$join->on('count_spans.context_id', '=', 'contexts.id')
			->where('count_spans.label', '=', $options['year']);
	})
	->select(
		Capsule::raw('GROUP_CONCAT(DISTINCT endpoints.oai_url SEPARATOR \'\n\') AS oai_url'),
		'endpoints.application',
		'endpoints.version',
		'endpoints.admin_email',
		'endpoints.earliest_datestamp',
		'endpoints.repository_name',
		'contexts.set_spec',
		Capsule::raw('COALESCE(MAX(count_spans.record_count), 0) AS record_count_' . $options['year']),
		Capsule::raw('MAX(contexts.total_record_count) AS total_record_count'),
		Capsule::raw('MAX(contexts.issn) AS issn'),
		Capsule::raw('MAX(contexts.country) AS country'),
		Capsule::raw('MAX(endpoints.last_completed_update) AS last_completed_update'),
		Capsule::raw('MIN(first_beacon) AS first_beacon'),
		Capsule::raw('MAX(last_beacon) AS first_beacon'),
		Capsule::raw('MAX(last_oai_response) AS last_oai_response')
	)
	->where('record_count', '>=', $options['minRecords'])
	->groupBy('endpoints.application', 'endpoints.version', 'endpoints.admin_email', 'endpoints.earliest_datestamp', 'endpoints.repository_name', 'contexts.set_spec');


$records = $query->get();
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
