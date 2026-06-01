<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Execute a single query

Exit code:
    0 - query executed successfully
    1 - query execution failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -q/--script=path_to_script              [OPTIONAL] Path to query script
                                                       Reads from STDIN when omitted
    -s/--silent                             [OPTIONAL] Only displays the query ID
    -o/--output=query_output_path           [OPTIONAL] Query result output location on S3 (no 's3://', no trailing '/')
                                                       Default value: QUERY_OUTPUT in configuration variable file
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

    php %1\$s -q PATH_TO_SCRIPT [-o QUERY_OUTPUT_PATH] [-g WORKGROUP_NAME] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION] [-w] [-s]
    php %1\$s --script PATH_TO_SCRIPT [--output QUERY_OUTPUT_PATH] [--workgroup WORKGROUP_NAME] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION] [--display] [--silent]

Example:
    echo "SELECT * FROM database.table" \
    | php %1\$s

    php %1\$s \
        -q ../../examples/query-single.sql \
        -o query_output_path \
        -g primary \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 'q:o:g:k:p:v:r:swf:h';
$longOpts = [
    'script:',
    'output:',
    'workgroup:',
    'catalog:',
    'profile:',
    'version:',
    'region:',
    'display',
    'silent',
    'env-file:',
    'help'
];

// set options
$options = setOptions($shortOpts, $longOpts, USAGE);

define('OPTION_SCRIPT', array_key_exists('q', $options) ? 'q' : 'script');
define('OPTION_OUTPUT', array_key_exists('o', $options) ? 'o' : 'output');
define('OPTION_WORKGROUP', array_key_exists('g', $options) ? 'g' : 'workgroup');
define('OPTION_CATALOG', array_key_exists('k', $options) ? 'k' : 'catalog');
define('OPTION_DISPLAY', array_key_exists('w', $options) ? 'w' : 'display');
define('OPTION_SILENT', array_key_exists('s', $options) ? 's' : 'silent');

// Initialize AWS configuration options
initAwsConfigOptions($options);
