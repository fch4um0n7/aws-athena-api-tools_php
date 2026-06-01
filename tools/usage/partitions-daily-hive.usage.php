<?php

// retrieve the name of the file that includes this usage file
define('INCLUDED_FILE', basename(get_included_files()[0]));

// modify the description here below
$whatItDoes = <<<EOS
Create partitions on a table holding HIVE-formatted data

Exit code:
    0 - partitions created successfully
    1 - partitions creation failed

Data must be stored under the following schema (HIVE):

    s3://BUCKET[/PREFIXES]/year=YEAR/month=MONTH/day=DAY

Not applicable to the following schema (non-HIVE):

    s3://BUCKET[/PREFIXES]/YEAR/MONTH/DAY

Table should be partitioned upon creation as follow:

    CREATE EXTERNAL TABLE IF NOT EXISTS database_name.table_name (
        column1 string
    )
    PARTITIONED BY (... string)
    ROW FORMAT ...
    LOCATION 's3://BUCKET/PREFIXES/year=YEAR/[month=MONTH[/day=DAY]]'
EOS;

define('USAGE', sprintf(USAGE_INTRO, $whatItDoes, INCLUDED_FILE, sprintf(<<<EOS
[options]

Options:
    -h/--help                               Show this help message and exit

    -t/--table=table_name                   [REQUIRED] Name of the table to create partitions on (format DATABASE_NAME.TABLE_NAME)
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

    php %1\$s -t TABLE_NAME [-o QUERY_OUTPUT_PATH] [-g WORKGROUP_NAME] [-k CATALOG_NAME] [-p AWS_PROFILE] [-v AWS_VERSION] [-r AWS_REGION]
    php %1\$s --table TABLE_NAME [--output QUERY_OUTPUT_PATH] [--workgroup WORKGROUP_NAME] [--catalog CATALOG_NAME] [--profile AWS_PROFILE] [--version AWS_VERSION] [--region AWS_REGION]

Example:
    php %1\$s \
        -t database_name.table_name \
        -o query_output_path \
        -g primary \
        -k AwsDataCatalog \
        -p default \
        -v latest \
        -r us-east-1
EOS, INCLUDED_FILE)));

$shortOpts = 't:o:g:k:p:v:r:f:h';
$longOpts = [
    'table:',
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

define('OPTION_TABLE', array_key_exists('t', $options) ? 't' : 'table');
define('OPTION_OUTPUT', array_key_exists('o', $options) ? 'o' : 'output');
define('OPTION_WORKGROUP', array_key_exists('g', $options) ? 'g' : 'workgroup');
define('OPTION_CATALOG', array_key_exists('k', $options) ? 'k' : 'catalog');

// Initialize AWS configuration options
initAwsConfigOptions($options);
