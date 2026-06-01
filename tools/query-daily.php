<?php

/**
 * Create tables for each month in the given date range
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/query-daily.usage.php";

// date format
const DATE_FORMAT = "Y-m-d";

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
    define('QUERY', $query);

    if (!isset($options[OPTION_BEGIN])) { throw new \Exception("Missing <start_date>!"); }
    $startDate = $options[OPTION_BEGIN];
    if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $startDate) || !validateDateTime($startDate, DATE_FORMAT)) { throw new \Exception("Wrong <start_date> format! Accepted format YYYY-MM-DD"); }
    $startDate = \DateTime::createFromFormat(DATE_FORMAT, $startDate);

    if (!isset($options[OPTION_END])) { $endDate = clone $startDate; }
    else {
        $endDate = $options[OPTION_END];
        if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $endDate) || !validateDateTime($endDate, DATE_FORMAT)) { throw new \Exception("Wrong <end_date> format! Accepted format YYYY-MM-DD"); }
        $endDate = \DateTime::createFromFormat(DATE_FORMAT, $endDate);

        if ($startDate > $endDate) { throw new \Exception("Date error: <end_date> must be posterior or equal to <start_date>!"); }
    }

    // interval in days
    $interval = $startDate->diff($endDate)->days;

    $output = $options[OPTION_OUTPUT] ?? DEFAULT_QUERY_OUTPUT;

    if (!isset($options[OPTION_QUERYTYPE])) { throw new \Exception("Missing <query_type>"); }
    $queryType = strtoupper($options[OPTION_QUERYTYPE]);
    if (!in_array($queryType, \FC\AWS\Athena::QUERY_TYPES, true)) {
        throw new \Exception(sprintf("Query type must be either %s or %s, %s given!", \FC\AWS\Athena::QUERY_TYPE_DDL, \FC\AWS\Athena::QUERY_TYPE_DML, $queryType));
    }

    if (isset($options[OPTION_MAXQUERY])) { $maxquery = (int)$options[OPTION_MAXQUERY]; }
    else { $maxquery = constant("\FC\AWS\Athena::AWS_DEFAULT_SIMULTANEOUS_" . $queryType . "_QUERIES"); }

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    $workgroup = $options[OPTION_WORKGROUP] ?? DEFAULT_WORKGROUP;

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

   // init Athena object
    $athena = instantiateAthena(
        new \Aws\Athena\AthenaClient($awsConfig),
        $queryType === \FC\AWS\Athena::QUERY_TYPE_DDL ? $maxquery : null,
        $queryType === \FC\AWS\Athena::QUERY_TYPE_DML ? $maxquery : null,
    );

    // output location on S3 for query results
    $queryOutputLocation = sprintf(QUERY_OUTPUT_LOCATION, $output);

    // execute all queries
    if (!isset($options[OPTION_DISPLAY])) { echo "Executing queries:\n"; }
    $currentDate = clone $startDate;
    $execDetails = [];
    do {
        // build query
        $y = $currentDate->format('Y');
        $m = $currentDate->format('m');
        $d = $currentDate->format('d');
        $query = sprintf(QUERY, $y, $m, $d);

        if (isset($options[OPTION_DISPLAY])) {
            echo "$query";
            exit(0);
        }

        // start query execution
        $execId = $athena->startQuery($query, $queryOutputLocation, $catalog, $workgroup);
        $execDetails[$execId] = "$y-$m-$d";

        // display the executing query details
        print $execId . "\t" . $currentDate->format(DATE_FORMAT) . PHP_EOL;

        // check if the limit of max simultaneous running queries is reached
        // and only resume the execution once within that limit
        while ($athena->isQueryLimitReached($queryType)) {}

        $currentDate->add(new \DateInterval('P1D'));
    } while ($currentDate <= $endDate);

    echo PHP_EOL;

    $exit = 0;

    // display query state once done running
    echo "Displaying queries result:\n";
    foreach ($athena->getQueries() as $query) {
        $loop = true;

        do {
            // get query id from the query array line
            $queryId = $athena->getQueryStaleId($query);

            // get the current state of that query
            $executionTime = '';
            $failureReason = '';
            $state = $athena->getQueryCurrentState($queryId, $executionTime, $failureReason);

            if (in_array($state, \FC\AWS\Athena::QUERY_STOP_STATES, true)) {
                $loop = false;
                print $queryId . "\t" . $execDetails[$queryId] . "\t" . $state . ($executionTime !== '' ? "\t" . $executionTime : '') . ($state === \FC\AWS\Athena::QUERY_STATE_FAILED ? "\t" . $failureReason : '') . PHP_EOL;

                if ($state !== \FC\AWS\Athena::QUERY_STATE_SUCCEEDED) {
                    $exit = 1;
                }
            }
        } while ($loop);
    }

    exit($exit);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
