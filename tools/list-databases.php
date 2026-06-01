<?php

/**
 * Detail one or all databases and output to json format
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/list-databases.usage.php";

// type of query/ies executed
const QUERY_TYPE = \FC\AWS\Athena::QUERY_TYPE_DML;

try {
    // init configuration parameters
    $detailed = isset($options[OPTION_DETAILED]);

    $catalog = $options[OPTION_CATALOG] ?? DEFAULT_CATALOG;

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // detail one or all databases
    if (isset($options[OPTION_DATABASE])) {
        if ($detailed) {
            echo json_encode($athena->getDatabaseDetails($options[OPTION_DATABASE], $catalog)) . PHP_EOL;
        } else {
            echo $athena->getDatabaseDetails($options[OPTION_DATABASE], $catalog)['Name'] . PHP_EOL;
        }
    } else {
        foreach ($athena->listAllDatabases($catalog) as $databaseName) {
            if ($detailed) {
                echo json_encode($athena->getDatabaseDetails($databaseName, $catalog)) . PHP_EOL;
            } else {
                echo $athena->getDatabaseDetails($databaseName, $catalog)['Name'] . PHP_EOL;
            }
        }
    }

    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
