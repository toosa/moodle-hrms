<?php
/**
 * CLI script to update HRIS service with all functions
 *
 * @package    local_hris
 * @copyright  2025 Prihantoosa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');

// Get the service
$service = $DB->get_record('external_services', ['shortname' => 'hris_service']);

if (!$service) {
    cli_error('HRIS service not found!');
}

echo "Found service: {$service->name} (ID: {$service->id})\n";

// Get all local_hris functions
$functions = $DB->get_records('external_functions', ['component' => 'local_hris']);

echo "Found " . count($functions) . " functions:\n";

foreach ($functions as $function) {
    echo "  - {$function->name}\n";
    
    // Check if already in service
    $exists = $DB->record_exists('external_services_functions', [
        'externalserviceid' => $service->id,
        'functionname' => $function->name
    ]);
    
    if (!$exists) {
        // Add to service
        $record = new stdClass();
        $record->externalserviceid = $service->id;
        $record->functionname = $function->name;
        $DB->insert_record('external_services_functions', $record);
        echo "    --> ADDED to service\n";
    } else {
        echo "    --> Already in service\n";
    }
}

// Purge caches
purge_all_caches();

echo "\nDone! All functions updated in service.\n";
