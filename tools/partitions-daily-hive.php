<?php

/**
 * Create day partitions on a table
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/partitions-daily-hive.usage.php";

// type of query/ies executed
const QUERY_TYPE = \FC\AWS\Athena::QUERY_TYPE_DDL;

try {
    // init configuration parameters
    if (!isset($options[OPTION_TABLE])) { throw new \Exception("Missing <table_name>!"); }
    $table = $options[OPTION_TABLE];

    $output = $options[OPTION_OUTPUT] ?? DEFAULT_QUERY_OUTPUT;

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    $workgroup = $options[OPTION_WORKGROUP] ?? DEFAULT_WORKGROUP;

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    $query = sprintf("MSCK REPAIR TABLE %s", $table);

    // output location for query results
    $queryOutputLocation = sprintf(QUERY_OUTPUT_LOCATION, $output);

    // execute all queries
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
