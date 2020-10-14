<?php

require_once('vendor/autoload.php');
require_once('classes/Contexts.inc.php');

use Illuminate\Database\Capsule\Manager as Capsule;

$db = new BeaconDatabase();
$capsule = $db->getCapsule();
$records = $capsule->table('contexts')
	->join('endpoints', 'contexts.endpoint_id', '=', 'endpoints.id')
	->select(
		Capsule::raw('GROUP_CONCAT(DISTINCT endpoints.oai_url SEPARATOR \'\n\') AS oai_url'),
		'contexts.set_spec',
		'endpoints.application',
		'endpoints.version',
		'endpoints.admin_email',
		'endpoints.earliest_datestamp',
		'endpoints.repository_name',
		Capsule::raw('MAX(endpoints.last_completed_update) AS last_completed_update'),
		Capsule::raw('MIN(first_beacon) AS first_beacon'),
		Capsule::raw('MAX(last_beacon) AS first_beacon'),
		Capsule::raw('MAX(last_oai_response) AS last_oai_response')
	)->groupBy('endpoints.application', 'endpoints.version', 'endpoints.admin_email', 'endpoints.earliest_datestamp', 'endpoints.repository_name', 'contexts.set_spec')
	->get();

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
