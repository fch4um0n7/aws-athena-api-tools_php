<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Stop a running query

Exit code:
    0 - query stopped successfully
    1 - query stop failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -i/--id=query_id                        [REQUIRED] ID of the query
    -p/--profile=aws_profile                [OPTIONAL] AWS profile to use credentials from
                                                       Default value: PROFILE in configuration variable file
    -v/--version=aws_version                [OPTIONAL] Version of the AWS webservice to utilize
                                                       Default value: VERSION in configuration variable file
    -r/--region=aws_region                  [OPTIONAL] AWS region to connect to
                                                       Default value: REGION in configuration variable file

    php %1\$s -i QUERY_ID [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s --id QUERY_ID [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]

Example:
    php %1\$s \
        -i a101e64e-52b1-4df2-a86c-1a2508a19eaf \
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
