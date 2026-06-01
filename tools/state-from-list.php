<?php

/**
 * Get the execution state of queries listed in a file (running, failed, succeeded, ...)
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/state-from-list.usage.php";

try {
    // init configuration parameters
    if (!isset($options[OPTION_LIST]) || !is_readable($options[OPTION_LIST])) { throw new \Exception("Missing or non-readable <path_to_list>!"); }
    $list = $options[OPTION_LIST];

    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    $file = fopen($list, 'r');
    if ($file === false) {
        throw new \Exception("Unable to open <path_to_list>!");
    }

    while (($rawLine = fgets($file)) !== false) {
        $line = trim($rawLine);
        $matches = [];

        if (!preg_match(sprintf('/%s/', ID_FORMAT), $line, $matches)) {
            print $line . PHP_EOL;
            continue;
        }

        if (isset($matches[0]) && $matches[0] !== '') {
            $queryID = $matches[0];
            $comments = trim(str_replace($queryID, '', $line));
            $executionTime = '';
            $failureReason = '';
            $state = $athena->getQueryCurrentState($queryID, $executionTime, $failureReason);

            print $queryID . "\t" . $state . ($executionTime !== '' ? "\t" . $executionTime : '') . ($state === \FC\AWS\Athena::QUERY_STATE_FAILED ? "\t" . $failureReason : '') . "\t" . $comments . PHP_EOL;
        }
    }

    fclose($file);
    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
    if (isset($file) && is_resource($file)) {
        fclose($file);
    }
    exit(1);
}

