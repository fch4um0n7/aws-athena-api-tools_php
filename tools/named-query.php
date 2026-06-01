<?php

/**
 * Create or delete a named query
 */

namespace FC\AWS;

require __DIR__ . "/imports/common.php";
require __DIR__ . "/usage/named-query.usage.php";

const CREATE = "CREATE";
const DELETE = "DELETE";

try {
    $query = '';

    // init configuration parameters
    if (isset($options[OPTION_CREATE])) {
        $sqlAction = CREATE;

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

        if (!isset($options[OPTION_DATABASE])) { throw new \Exception("Missing option <database_name>!"); }
        $database = $options[OPTION_DATABASE];

        if (!isset($options[OPTION_NAME])) { throw new \Exception("Missing option <query_name>!"); }
        $name = $options[OPTION_NAME];

        if (!isset($options[OPTION_DESCRIPTION])) { throw new \Exception("Missing option <query_description>!"); }
        else { $description = $options[OPTION_DESCRIPTION]; }
    } else {
        if (!isset($options[OPTION_DELETE])) { throw new \Exception("Either option <create_name_query> or <delete_named_query> must be provided!"); }
        else {
            $sqlAction = DELETE;

            if (!isset($options[OPTION_ID])) { throw new \Exception("Missing option <query_id>!"); }
            $id = $options[OPTION_ID];
        }
    }

    // AWS Athena client configuration
    $awsConfig = getAwsConfig($options);

    // init Athena object
    $athena = instantiateAthena(new \Aws\Athena\AthenaClient($awsConfig));

    // create or delete named query
    if ($sqlAction === CREATE) {
        if (!isset($options[OPTION_SILENT])) { echo "Creating named query:" . PHP_EOL; }
        $queryId = $athena->createNamedQuery($query, $database, $name, $description);

        if (isset($options[OPTION_SILENT])) { print $queryId; }
        else { print $queryId . PHP_EOL; }

        if (!isset($options[OPTION_SILENT])) { print "Created successfully" . PHP_EOL; }
    } elseif ($sqlAction === DELETE) {
        echo "Deleting named query:" . PHP_EOL;
        print $id . PHP_EOL;
        $athena->deleteNamedQuery($id);
        print "Deleted successfully" . PHP_EOL;
    }

    exit(0);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}
