<?php

/**
 * Create/Drop tables for each month in the given date range
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/query-monthly.usage.php";

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
    define('QUERY', $query);

    if (!isset($options[OPTION_BEGIN])) { throw new \Exception("Missing <start_date>!"); }
    $startDate = $options[OPTION_BEGIN];
    if (!preg_match('/[0-9]{4}-[0-9]{2}/', $startDate) || !validateDateTime($startDate, 'Y-m')) { throw new \Exception("Wrong <start_date> format! Accepted format YYYY-MM"); }
    $startDate = explode('-', $startDate);
    $startDate[YEAR_IDX] = intval($startDate[YEAR_IDX]);
    $startDate[MONTH_IDX] = intval($startDate[MONTH_IDX]);

    if (!isset($options[OPTION_END])) { $endDate = $startDate; }
    else {
        $endDate = $options[OPTION_END];
        if (!preg_match('/[0-9]{4}-[0-9]{2}/', $endDate) || !validateDateTime($endDate, 'Y-m')) { throw new \Exception("Wrong <end_date> format! Accepted format YYYY-MM"); }
        $endDate = explode('-', $endDate);
        $endDate[YEAR_IDX] = intval($endDate[YEAR_IDX]);
        $endDate[MONTH_IDX] = intval($endDate[MONTH_IDX]);

        if (($endDate[YEAR_IDX] === $startDate[YEAR_IDX] && $endDate[MONTH_IDX] < $startDate[MONTH_IDX]) || $endDate[YEAR_IDX] < $startDate[YEAR_IDX]) {
            throw new \Exception("Date error: <end_date> must be posterior or equal to <start_date>!");
        }
    }

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
    $execDetails = [];
    $m = $startDate[MONTH_IDX];
    for ($y = $startDate[YEAR_IDX]; $y <= $endDate[YEAR_IDX]; $y++) {
        for (; $m <= 12; $m++) {
            if ($y === $endDate[YEAR_IDX] && $m > $endDate[MONTH_IDX]) { break; }

            // build query
            $month = substr('00' . $m, -2);
            $query = sprintf(QUERY, $y, $month);

            if (isset($options[OPTION_DISPLAY])) {
                echo "$query";
                exit(0);
            }

            // start query execution
            $execId = $athena->startQuery($query, $queryOutputLocation, $catalog, $workgroup);
            $execDetails[$execId] = "$y-$month";

            // display the executing query details
            print $execId . "\t" . "$y-$month" . PHP_EOL;

            // check if the limit of max simultaneous running queries is reached
            // and only resume the execution once within that limit
            while ($athena->isQueryLimitReached($queryType)) {}
        }

        $m = 1;
    }

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
