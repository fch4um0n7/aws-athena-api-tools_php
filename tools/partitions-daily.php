<?php

/**
 * Create day partitions on a table
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/partitions-daily.usage.php";

// default column name for partitioning
const DEFAULT_PARTITION_COLUMN = 'day';

try {
    // init configuration parameters
    if (!isset($options[OPTION_TABLE])) { throw new \Exception("Missing <table_name>!"); }
    $table = $options[OPTION_TABLE];

    if (!isset($options[OPTION_DATA])) { throw new \Exception("Missing <path_to_data>!"); }
    $data = $options[OPTION_DATA];

    if (!isset($options[OPTION_YEAR])) { throw new \Exception("Missing <year_of_data>!"); }
    $year = intval($options[OPTION_YEAR]);

    if (!isset($options[OPTION_MONTH])) { throw new \Exception("Missing <month_of_data>!"); }
    $month = substr("00" . (string)intval($options[OPTION_MONTH]), -2);

    $maxDays = cal_days_in_month(CAL_GREGORIAN, intval($month), $year);
    if (!($maxDays > 0)) { throw new \Exception("Max days for the month must superior to 0"); }

    if (!isset($options[OPTION_BEGIN])) { $startDate = '01'; }
    else { $startDate = $options[OPTION_BEGIN]; }
    if (intval($startDate) === 0) { throw new \Exception("Invalid start date: $startDate"); }

    if (!isset($options[OPTION_END])) { $endDate = $maxDays; }
    else {
        $endDate = $options[OPTION_END];
        if (intval($endDate) === 0) { throw new \Exception("Invalid end date: $endDate"); }
    }

    if ($endDate < $startDate) { throw new \Exception("End date $endDate must be posterior to start date $startDate!"); }

    $output = $options[OPTION_OUTPUT] ?? DEFAULT_QUERY_OUTPUT;

    $column = (isset($options[OPTION_COLUMN]) && $options[OPTION_COLUMN] !== '' ? $options[OPTION_COLUMN] : DEFAULT_PARTITION_COLUMN);

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    $workgroup = $options[OPTION_WORKGROUP] ?? DEFAULT_WORKGROUP;

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    $query = "ALTER TABLE $table ADD PARTITION ($column='%s') location 's3://$data/%s/'";

    // output location for query results
    $queryOutputLocation = sprintf(QUERY_OUTPUT_LOCATION, $output);

    // execute all queries
    echo "Executing queries:\n";
    $execDetails = [];
    for ($d = intval($startDate); $d <= intval($endDate); $d++) {
        // execute a query
        $day = substr('0'.$d, -2);
        $queryScript = sprintf($query, $day, $day);
        $execId = $athena->startQuery($queryScript, $queryOutputLocation, $catalog, $workgroup);
        $execDetails[$execId] = "$year-$month-$day";

        // display the executing query details
        print $execId . "\t" . "$year-$month-$day" . PHP_EOL;

        // check if the limit of max simultaneous running queries is reached
        // and only resume the execution once within that limit
        while ($athena->isQueryLimitReached(\FC\AWS\Athena::QUERY_TYPE_DDL)) {}
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
