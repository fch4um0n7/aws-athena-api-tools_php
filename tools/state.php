<?php

/**
 * Get the execution state of a query (running, failed, succeeded, ...)
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/state.usage.php";

try {
    // init configuration parameters
    if (!isset($options[OPTION_ID])) { throw new \Exception("Missing <query_id>!"); }
    $id = $options[OPTION_ID];
    if (!preg_match(sprintf('/^%s$/', ID_FORMAT), $id)) {
        throw new \Exception("Wrong <id> format! Accepted format " . ID_FORMAT);
    }

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // display query state
    $executionTime = '';
    $failureReason = '';
    $state = $athena->getQueryCurrentState($id, $executionTime, $failureReason);
    print $id . "\t" . $state . ($executionTime !== '' ? "\t" . $executionTime : '') . ($state === \FC\AWS\Athena::QUERY_STATE_FAILED ? "\t" . $failureReason : '') . PHP_EOL;

    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

