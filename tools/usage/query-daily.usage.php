<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Execute queries for each day in the given date range

Exit code:
    0 - all queries executed successfully
    1 - at least one query execution failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -q/--script=path_to_script              [OPTIONAL] Path to table creation script¹
                                                       Reads from STDIN when omitted
    -b/--begin=start_date                   [REQUIRED] Start date (format YYYY-MM-DD)
    -e/--end=end_date                       [OPTIONAL] End date (format YYYY-MM-DD)
                                                       Default value: start date
    -u/--maxquery=max_simultaneous_queries  [OPTIONAL] Max queries to run simultaneously
                                                       Default value: see your AWS Athena service limits
    -o/--output=query_output_path           [OPTIONAL] Query result output location on S3 (no 's3://', no trailing '/')
                                                       Default value: QUERY_OUTPUT in configuration variable file
    -x/--querytype=query_type               [REQUIRED] Type of query to execute used to set rate limits (either DML or DDL)
    -g/--workgroup=workgroup_name           [OPTIONAL] Athena workgroup to be used by the query
                                                       Default value: WORKGROUP in configuration variable file
    -k/--catalog=catalog_name               [OPTIONAL] Athena data source catalog
                                                       Default value: CATALOG in configuration variable file
    -p/--profile=aws_profile                [OPTIONAL] AWS profile to use credentials from
                                                       Default value: PROFILE in configuration variable file
    -v/--version=aws_version                [OPTIONAL] Version of the AWS webservice to utilize
                                                       Default value: VERSION in configuration variable file
    -r/--region=aws_region                  [OPTIONAL] AWS region to connect to
                                                       Default value: REGION in configuration variable file
    -w/--display                            [OPTIONAL] Only displays query for syntax verification

    ¹ Inside the script (or STDIN), < YEAR > is replaced < %%1\$s >, < MONTH > is replaced by < %%2\$s > and < DAY > is replaced by < %%3\$s >

    php %1\$s -q PATH_TO_SCRIPT -b START_DATE [-e END_DATE] [-u MAX_SIMULTANEOUS_QUERIES] [-o QUERY_OUTPUT_PATH] -x QUERY_TYPE [-g WORKGROUP_NAME] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION] [-w]
    php %1\$s --script PATH_TO_SCRIPT --begin START_DATE [--end END_DATE] [--maxquery MAX_SIMULTANEOUS_QUERIES] [--output QUERY_OUTPUT_PATH] --querytype QUERY_TYPE [--workgroup WORKGROUP_NAME] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION] [--display]

Example:
    echo "SELECT * FROM database.table_%%1\\\$s%%2\\\$s WHERE day = '%%3\\\$s'" \
    | php %1\$s \
        -b start_date \
        -x DML

    php %1\$s \
        -q ../../examples/query-daily.sql \
        -b start_date \
        -e end_date \
        -u 4 \
        -o query_output_path \
        -x DML \
        -g primary \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 'q:b:e:u:o:x:g:k:p:v:r:wf:h';
$longOpts = [
    'script:',
    'begin:',
    'end:',
    'maxquery:',
    'output:',
    'querytype:',
    'workgroup:',
    'catalog:',
    'profile:',
    'version:',
    'region:',
    'display',
    'env-file:',
    'help'
];

// set options
$options = setOptions($shortOpts, $longOpts, USAGE);

define('OPTION_SCRIPT', array_key_exists('q', $options) ? 'q' : 'script');
define('OPTION_BEGIN', array_key_exists('b', $options) ? 'b' : 'begin');
define('OPTION_END', array_key_exists('e', $options) ? 'e' : 'end');
define('OPTION_MAXQUERY', array_key_exists('u', $options) ? 'u' : 'maxquery');
define('OPTION_OUTPUT', array_key_exists('o', $options) ? 'o' : 'output');
define('OPTION_QUERYTYPE', array_key_exists('x', $options) ? 'x' : 'querytype');
define('OPTION_WORKGROUP', array_key_exists('g', $options) ? 'g' : 'workgroup');
define('OPTION_CATALOG', array_key_exists('k', $options) ? 'k' : 'catalog');
define('OPTION_DISPLAY', array_key_exists('w', $options) ? 'w' : 'display');

// Initialize AWS configuration options
initAwsConfigOptions($options);
