<?php

/**
 * Execute a single query
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/query.usage.php";

const YEAR_IDX = 0;
const MONTH_IDX = 1;

try {
    // init configuration parameters
    if (!isset($options[OPTION_SCRIPT])) {
        stream_set_blocking(STDIN, false);
        $query = stream_get_contents(STDIN);
        if ($query === '') { throw new \Exception("No query found!"); }
    } else {
        $script = $options[OPTION_SCRIPT];
        if (!is_readable($script)) { throw new \Exception(sprintf("Script file not found at %s!", $script)); }
        $query = file_get_contents($script);
    }

    if (isset($options[OPTION_DISPLAY])) {
        echo "$query";
        exit(0);
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

    // start query execution
    if (!isset($options[OPTION_SILENT])) { echo "Executing query:" . PHP_EOL; }
    $execId = $athena->startQuery($query, $queryOutputLocation, $catalog, $workgroup);

    // display the executing query details
    if (!isset($options[OPTION_SILENT])) { print $execId . PHP_EOL; }

    $exit = 0;
    $loop = true;
    do {
        // get the current state of that query
        $executionTime = '';
        $failureReason = '';
        $state = $athena->getQueryCurrentState($execId, $executionTime, $failureReason);

        if (in_array($state, \FC\AWS\Athena::QUERY_STOP_STATES, true)) {
            $loop = false;

            if (!isset($options[OPTION_SILENT])) {
                print "Query result:" . PHP_EOL;
                print $execId . "\t" . $state . ($executionTime !== '' ? "\t" . $executionTime : '') . ($state === \FC\AWS\Athena::QUERY_STATE_FAILED ? "\t" . $failureReason : '') . PHP_EOL;
            }

            if ($state !== \FC\AWS\Athena::QUERY_STATE_SUCCEEDED) {
                if (isset($options[OPTION_SILENT])) {
                    print '';
                }
                $exit = 1;
            } else {
                if (isset($options[OPTION_SILENT])) {
                    print $execId;
                } else {
                    print '';
                }
            }
        }
    } while ($loop);

    exit($exit);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
