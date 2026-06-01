<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Detail one or all databases and output to json format

Exit code:
    0 - databases listed successfully
    1 - databases list execution failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -n/--database=database_name             [OPTIONAL] Database name
    -a/--detailed                           [OPTIONAL] Display database full details
    -k/--catalog=catalog_name               [OPTIONAL] Data source catalog
                                                       Default value: CATALOG in configuration variable file
    -p/--profile=aws_profile                [OPTIONAL] AWS profile to use credentials from
                                                       Default value: PROFILE in configuration variable file
    -v/--version=aws_version                [OPTIONAL] Version of the AWS webservice to utilize
                                                       Default value: VERSION in configuration variable file
    -r/--region=aws_region                  [OPTIONAL] AWS region to connect to
                                                       Default value: REGION in configuration variable file

    php %1\$s [-n DATABASE_NAME] [-a] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s [--database DATABASE_NAME] [--detailed] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]

Example:
    php %1\$s \
        -n sampledb \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1

    php %1\$s \
        -a \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 'n:ak:p:v:r:f:h';
$longOpts = [
    'database:',
    'detailed',
    'catalog:',
    'profile:',
    'version:',
    'region:',
    'env-file:',
    'help'
];

// set options
$options = setOptions($shortOpts, $longOpts, USAGE);

define('OPTION_DATABASE', array_key_exists('n', $options) ? 'n' : 'database');
define('OPTION_CATALOG', array_key_exists('k', $options) ? 'k' : 'catalog');
define('OPTION_DETAILED', array_key_exists('a', $options) ? 'a' : 'detailed');

// Initialize AWS configuration options
initAwsConfigOptions($options);
