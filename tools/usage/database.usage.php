<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Create/Drop database

Exit code:
    0 - database created/dropped successfully
    1 - database creation/drop failed
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -c/--create                             [REQUIRED] Create a new database¹
    -d/--drop                               [REQUIRED] Drop an existing database¹
    -t/--cascade                            [OPTIONAL] Drop all tables inside the database (use together with -d/--drop, ignored otherwise)
                                                       If omitted and tables are present, the database will not be dropped
    -n/--database=database_name             [REQUIRED] Name of the database
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

    /!\ ¹Either -c/--create or -d/--drop

    php %1\$s -c -n DATABASE_NAME [-o QUERY_OUTPUT_PATH] [-g WORKGROUP_NAME] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s --create --database DATABASE_NAME [--output QUERY_OUTPUT_PATH] [--workgroup WORKGROUP_NAME] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]
    php %1\$s -d [-t] -n DATABASE_NAME [-o QUERY_OUTPUT_PATH] [-g WORKGROUP_NAME] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s --drop [--cascade] --database DATABASE_NAME [--output QUERY_OUTPUT_PATH] [--workgroup WORKGROUP_NAME] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]

Example:
    php %1\$s \
        -c \
        -n database_name \
        -o query_output_path \
        -g primary \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1

    php %1\$s \
        -d [-t] \
        -n database_name \
        -o query_output_path \
        -g primary \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 'cdtn:o:g:k:p:v:r:f:h';
$longOpts = [
    'create',
    'drop',
    'cascade',
    'database:',
    'output:',
    'workgroup:',
    'catalog:',
    'profile:',
    'version:',
    'region:',
    'env-file:',
    'help'
];

// set options
$options = setOptions($shortOpts, $longOpts, USAGE);

define('OPTION_CREATE', array_key_exists('c', $options) ? 'c' : 'create');
define('OPTION_DROP', array_key_exists('d', $options) ? 'd' : 'drop');
define('OPTION_CASCADE', array_key_exists('t', $options) ? 't' : 'cascade');
define('OPTION_DATABASE', array_key_exists('n', $options) ? 'n' : 'database');
define('OPTION_OUTPUT', array_key_exists('o', $options) ? 'o' : 'output');
define('OPTION_WORKGROUP', array_key_exists('g', $options) ? 'g' : 'workgroup');
define('OPTION_CATALOG', array_key_exists('k', $options) ? 'k' : 'catalog');

// Initialize AWS configuration options
initAwsConfigOptions($options);
