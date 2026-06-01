<?php

/**
 * Detail one or all tables of a database and output to json format
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/list-tables.usage.php";

// type of query/ies executed
const QUERY_TYPE = \FC\AWS\Athena::QUERY_TYPE_DML;

try {
    // init configuration parameters
    if (!isset($options[OPTION_DATABASE])) { throw new \Exception("Missing option <database_name>!"); }
    $database = $options[OPTION_DATABASE];

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    $tables = $options[OPTION_TABLE] ?? '';

    $detailed = isset($options[OPTION_DETAILED]);

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // detail one or all tables
    foreach ($athena->listAllTablesDetails($database, $catalog, $tables) as $tableDetails) {
        if ($detailed) {
            echo json_encode($tableDetails, JSON_PRETTY_PRINT) . PHP_EOL;
        } else {
            echo $tableDetails['Name'] . PHP_EOL;
        }
    }

    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
