<?php

/**
 * Detail one or all named queries and output to json format
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/list-named-queries.usage.php";

// type of query/ies executed
const QUERY_TYPE = \FC\AWS\Athena::QUERY_TYPE_DML;

try {
    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // detail one or all named queries
    if (isset($options[OPTION_ID])) {
        echo json_encode($athena->getNamedQueryDetails($options[OPTION_ID]), JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        $nextPaginationToken = '';
        do {
            foreach($athena->listNamedQueries($nextPaginationToken) as $namedQueryId) {
                echo json_encode($athena->getNamedQueryDetails($namedQueryId), JSON_PRETTY_PRINT) . PHP_EOL;
            }
        } while ($nextPaginationToken !== '');
    }

    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
