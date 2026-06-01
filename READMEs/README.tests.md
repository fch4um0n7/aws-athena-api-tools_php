## Tests
This [test](../tests/test.sh) script allows to tests every tools of this library.

Make sure to read **Requirements**, **Installation** and **Configuration** first.
**For safety, a confirmation to delete data on s3 and drop database/tables is required at start.**

Tested on Linux with PHP 8.5+.

It requires the database `sampledb` and performs the following:
1. list database `sampledb`
2. create a new database
3. create test data by extracting from `sampledb.elb_logs` table and creating tables with daily data
4. create table for test data with multiple days data
5. create day partitions on test data tables
6. select data from multiple days table
7. select several days data from single day table
8. display query result files on s3
9. detail tables in the database
10. drop database and all tables
11. delete data from s3
12. create named query
13. detail named query
14. delete named query

Usage:
```shell
/bin/bash test.sh \
    -d DATABASE_TO_CREATE \
    -y YEAR_OF_DATA_TO_EXTRACT \
    -m MONTH_OF_DATA_TO_EXTRACT
```

Example:
```shell
/bin/bash test.sh \
    -d aws_athena_api_tools_tests \
    -y 2015 \
    -m 01
```

Output:
See expected [output](../tests/output.txt) for more details.

