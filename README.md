# Toolkit for AWS Athena API

![GitHub](https://img.shields.io/github/license/FrancoisChaumont/aws-athena-api-tools)
[![contributions welcome](https://img.shields.io/badge/contributions-welcome-brightgreen?style=flat)](https://github.com/FrancoisChaumont/aws-athena-api-tools/issues)
![GitHub release](https://img.shields.io/github/release/FrancoisChaumont/aws-athena-api-tools)
[![GitHub issues](https://img.shields.io/github/issues/FrancoisChaumont/aws-athena-api-tools)](https://github.com/FrancoisChaumont/aws-athena-api-tools/issues)
[![GitHub issues](https://img.shields.io/github/issues-closed/FrancoisChaumont/aws-athena-api-tools)](https://github.com/FrancoisChaumont/aws-athena-api-tools/issues)

![GitHub watchers](https://img.shields.io/github/watchers/FrancoisChaumont/aws-athena-api-tools?style=social)
[![GitHub stars](https://img.shields.io/github/stars/FrancoisChaumont/aws-athena-api-tools)](https://github.com/FrancoisChaumont/aws-athena-api-tools/stargazers)
![GitHub forks](https://img.shields.io/github/forks/FrancoisChaumont/aws-athena-api-tools?style=social)
![GitHub contributors](https://img.shields.io/github/contributors/FrancoisChaumont/aws-athena-api-tools)
![Github All Releases](https://img.shields.io/github/downloads/FrancoisChaumont/aws-athena-api-tools/total)

## Introduction
**What it does?** It allows you to do the following from the command line:
- create/drop database
- execute a single query
- execute multiple queries simultaneously while remaining within your max rate limits
- create partitions on non-hive or hive formatted data
- get one or multiple queries current states
- stop a running query
- create a named query
- list & detail named queries
- list & detail databases
- list & detail database tables

## Requirements
- [PHP](https://www.php.net/) ^8.5
- [aws/aws-sdk-php](https://github.com/aws/aws-sdk-php) ^3.175
- [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv) ^5.3
- [Composer](https://getcomposer.org)
- [AWS CLI](https://docs.aws.amazon.com/cli/latest/userguide/cli-chap-install.html)
- AWS_ACCESS_KEY_ID & AWS_SECRET_ACCESS_KEY¹

> ¹ The SDK should detect the credentials from environment variables (via AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY), an AWS credentials INI file in your HOME directory, AWS Identity and Access Management (IAM) instance profile credentials, or credential providers

## Installation
Download a copy of this repository and run the following:
```
composer install
```

## Configuration
Modify the following variables inside the file [.env](.env) for default values to use when related options are omitted
- `PROFILE`: AWS profile from ~/.aws/credentials
- `VERSION`: AWS webservice version
- `REGION`: AWS region to connect to
- `CATALOG`: Athena data source catalog
- `WORKGROUP`: Athena workgroup
- `QUERY_OUTPUT`: S3 bucket for query results
- `AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES`¹: level 1 queries max calls per second
- `AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES`¹⁺⁰: level 1 queries max burst capacity
- `AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES`²: level 2 queries max calls per second
- `AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES`²⁺⁰: level 2 queries max burst capacity
- `AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES`³: level 3 queries max calls per second
- `AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES`³⁺⁰: level 3 queries max burst capacity
- `AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES`⁴: level 4 queries max calls per second
- `AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES`⁴⁺⁰: level 4 queries max burst capacity
- `AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES`⁵: level 5 queries max calls per second
- `AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES`⁵⁺⁰: level 5 queries max burst capacity
- `AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES`⁶: max simultaneous DDL queries
- `AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES`⁷: max simultaneous DML queries

¹BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions
²CreateNamedQuery, DeleteNamedQuery, GetNamedQuery
³BatchGetQueryExecution
⁴StartQueryExecution, StopQueryExecution
⁵GetQueryExecution, GetQueryResults - `a value higher than 2 will exceed the max rate limit`
⁶create table, create table add partition
⁷select, create table as (CTAS)

⁰max burst capacity not yet implemented

## Important
- Make sure to double % inside query files for other than parameters passed to the query or they will be replaced by sprintf

Example passing year + month to constitute the table name:
```sql
SELECT DATE_FORMAT(FROM_UNIXTIME(1614716423), '%%Y-%%m-%%d %%H:%%i:%%S')
FROM database.table_name_%1$s%2$s
```

## The tools
See tools [documentation](READMEs/README.tools.md) for more details.

## Testing
See tests [documentation](READMEs/README.tests.md) for more details.

## AWS documentation
AWS documentation:
- AWS SDK [Basic Usage](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_basic-usage.html)
- AWS SDK [API documentation for Athena](https://docs.aws.amazon.com/aws-sdk-php/v3/api/namespace-Aws.Athena.html)
- AWS SDK for PHP v3 [Getting Started](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_index.html)
- AWS Athena [Service Limits](https://docs.aws.amazon.com/athena/latest/ug/service-limits.html)
- List of [AWS regions](http://docs.aws.amazon.com/general/latest/gr/rande.html)
- Data [Partitioning](https://docs.aws.amazon.com/athena/latest/ug/partitions.html)

## TODO
Methods:
- BatchGetNamedQuery
- BatchGetQueryExecution
- CreateDataCatalog
- CreatePreparedStatement
- CreateWorkGroup
- DeleteDataCatalog
- DeletePreparedStatement
- DeleteWorkGroup
- GetDataCatalog
- GetPreparedStatement
- GetQueryResults
- GetWorkGroup
- ListDataCatalogs
- ListEngineVersions
- ListPreparedStatements
- ListQueryExecutions
- ListTagsForResource
- ListWorkGroups
- TagResource
- UntagResource
- UpdateDataCatalog
- UpdatePreparedStatement
- UpdateWorkGroup

Others:
- implement burst capacity?
