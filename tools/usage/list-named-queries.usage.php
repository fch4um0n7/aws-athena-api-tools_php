<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Detail one or all named queries and output to json format

Exit code:
    0 - named queries listed successfully
    1 - named queries list execution failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -i/--id=query_id                        [OPTIONAL] Named query ID
    -p/--profile=aws_profile                [OPTIONAL] AWS profile to use credentials from
                                                       Default value: PROFILE in configuration variable file
    -v/--version=aws_version                [OPTIONAL] Version of the AWS webservice to utilize
                                                       Default value: VERSION in configuration variable file
    -r/--region=aws_region                  [OPTIONAL] AWS region to connect to
                                                       Default value: REGION in configuration variable file

    php %1\$s [-i QUERY_ID] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s [--id QUERY_ID] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]

Example:
    php %1\$s \
        -i d9d3c918-7d3d-454d-a9af-e2721ecf0bf6 \
        -p default \
        -v latest \
        -r us-east-1

    php %1\$s \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 'i:p:v:r:f:h';
$longOpts = [
    'id:',
    'profile:',
    'version:',
    'region:',
    'env-file:',
    'help'
];

// set options
$options = setOptions($shortOpts, $longOpts, USAGE);

define('OPTION_ID', array_key_exists('i', $options) ? 'i' : 'id');

// Initialize AWS configuration options
initAwsConfigOptions($options);
