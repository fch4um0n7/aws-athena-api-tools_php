<?php

/**
 * Create/Drop a database
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/database.usage.php";

// query actions
const CREATE = "CREATE";
const DROP = "DROP";
const CASCADE = "CASCADE";

$cascade = '';

try {
    // init configuration parameters
    if (!isset($options[OPTION_DATABASE])) { throw new \Exception("Missing option <database_name>!"); }
    else { $database = $options[OPTION_DATABASE]; }

    if (isset($options[OPTION_CREATE])) {
        $sqlAction = CREATE;
    } else {
        if (!isset($options[OPTION_DROP])) { throw new \Exception("Either option <create_database> or <drop_database> must be provided!"); }
        else {
            $sqlAction = DROP;
            if (isset($options[OPTION_CASCADE])) { $cascade = CASCADE; }
        }
    }

    $output = $options[OPTION_OUTPUT] ?? DEFAULT_QUERY_OUTPUT;

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    $workgroup = $options[OPTION_WORKGROUP] ?? DEFAULT_WORKGROUP;

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // output location on S3 for query results
    $queryOutputLocation = sprintf(QUERY_OUTPUT_LOCATION, $output);

    // build query
    $query = "$sqlAction DATABASE $database $cascade";

    // start query execution
    echo "Executing query:\n";
    $execId = $athena->startQuery($query, $queryOutputLocation, $catalog, $workgroup);

    // display the executing query details
    print $execId . PHP_EOL;

    $exit = 0;
    $loop = true;
    do {
        // get the current state of that query
        $executionTime = '';
        $failureReason = '';
        $state = $athena->getQueryCurrentState($execId, $executionTime, $failureReason);

        if (in_array($state, \FC\AWS\Athena::QUERY_STOP_STATES, true)) {
            $loop = false;
            print "Query result:\n";
            print $execId . "\t" . $state . ($executionTime !== '' ? "\t" . $executionTime : '') . ($state === \FC\AWS\Athena::QUERY_STATE_FAILED ? "\t" . $failureReason : '') . PHP_EOL;
            if ($state !== \FC\AWS\Athena::QUERY_STATE_SUCCEEDED) { $exit = 1; }
        }
    } while ($loop);

    exit($exit);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
