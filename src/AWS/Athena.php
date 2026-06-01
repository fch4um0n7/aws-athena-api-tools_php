<?php

namespace FC\AWS;

/**
 * Execute queries on AWS Athena using AWS SDK for PHP v3
 */
class Athena
{
    // query states
    const QUERY_STATE_RUNNING = 'RUNNING';
    const QUERY_STATE_QUEUED = 'QUEUED';
    const QUERY_STATE_SUCCEEDED = 'SUCCEEDED';
    const QUERY_STATE_FAILED = 'FAILED';
    const QUERY_STATE_CANCELLED = 'CANCELLED';

    // stopped states (not running)
    const QUERY_STOP_STATES = [
        self::QUERY_STATE_SUCCEEDED,
        self::QUERY_STATE_FAILED,
        self::QUERY_STATE_CANCELLED
    ];

    // query string max length in bytes
    const QUERY_MAX_LENGTH = 262144;

    // query types
    const QUERY_TYPE_DDL = "DDL";
    const QUERY_TYPE_DML = "DML";
    const QUERY_TYPES = [
        self::QUERY_TYPE_DDL,
        self::QUERY_TYPE_DML
    ];

    // Athena API default limit per account
    // level 1 queries: BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions
    const AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES = 5;
    const AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES = 10;
    // level 2 queries: CreateNamedQuery, DeleteNamedQuery, GetNamedQuery
    const AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES = 5;
    const AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES = 20;
    // level 3 queries: BatchGetQueryExecution
    const AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES = 20;
    const AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES = 40;
    // level 4 queries: StartQueryExecution, StopQueryExecution
    const AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES = 20;
    const AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES = 80;
    // level 5 queries: GetQueryExecution, GetQueryResults
    const AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES = 100;
    const AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES = 200;

    // DDL queries: create table, create table add partition
    const AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES = 20;
    // DML queries: select, create table as (CTAS)
    const AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES = 20;

    /**
     * Athena API limit per account
     */

    /** @var int $level1QueriesMaxCalls max calls per second -> BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions */
    private int $level1QueriesMaxCalls;
    /** @var int $level2QueriesMaxCalls max calls per second -> CreateNamedQuery, DeleteNamedQuery, GetNamedQuery */
    private int $level2QueriesMaxCalls;
    /** @var int $level3QueriesMaxCalls max calls per second -> BatchGetQueryExecution */
    private int $level3QueriesMaxCalls;
    /** @var int $level4QueriesMaxCalls max calls per second -> StartQueryExecution, StopQueryExecution */
    private int $level4QueriesMaxCalls;
    /** @var int $level5QueriesMaxCalls max calls per second -> GetQueryExecution, GetQueryResults */
    private int $level5QueriesMaxCalls;
    /** @var int $level1QueriesBurstCapacity burst capacity -> BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions */
    private int $level1QueriesBurstCapacity;
    /** @var int $level2QueriesBurstCapacity burst capacity -> CreateNamedQuery, DeleteNamedQuery, GetNamedQuery */
    private int $level2QueriesBurstCapacity;
    /** @var int $level3QueriesBurstCapacity burst capacity -> BatchGetQueryExecution */
    private int $level3QueriesBurstCapacity;
    /** @var int $level4QueriesBurstCapacity burst capacity -> StartQueryExecution, StopQueryExecution */
    private int $level4QueriesBurstCapacity;
    /** @var int $level5QueriesBurstCapacity burst capacity -> GetQueryExecution, GetQueryResults */
    private int $level5QueriesBurstCapacity;
    /** @var int $ddlQueriesSimultaneous max simultaneous DDL queries */
    private int $ddlQueriesSimultaneous;
    /** @var int $dmlQueriesSimultaneous max simultaneous DML queries */
    private int $dmlQueriesSimultaneous;

    private readonly \Aws\Athena\AthenaClient $athenaClient;
    private array $queries = [];

    /**
     * Reset the query details (IDs and states)
     *
     * @param boolean $force true to force reset with existing running queries
     * @return boolean false if force flag is not set and there is at least one running query, true otherwise
     */
    public function resetQueries(bool $force = false): bool
    {
        if (!$force && in_array(self::QUERY_STATE_RUNNING, array_column($this->queries, 'state'), true)) {
            return false;
        }

        $this->queries = [];
        return true;
    }

    /**
     * Get a copy of the array containing query IDs and states
     *
     * @return array
     */
    public function getQueries(): array { return $this->queries; }

    /**
     * Get the total of query executions
     *
     * @return integer
     */
    public function getTotalQueries(): int { return count($this->queries); }

    /**
     * Get a stale query state (not current, see getQueryCurrentState) from a query detail array (identical to query['state'])
     *
     * @param array $query single query details
     * @return string query state (RUNNING, QUEUED, SUCCEEDED, FAILED or CANCELLED)
     */
    public function getQueryStaleState(array $query): string { return $query['state']; }

    /**
     * Get a stale query id from a query detail array (identical to query['execId'])
     *
     * @param array $query single query details
     * @return string query id
     */
    public function getQueryStaleId(array $query): string { return $query['execId']; }

    /**
     * Constructor
     * level 1 queries: BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions
     * level 2 queries: CreateNamedQuery, DeleteNamedQuery, GetNamedQuery
     * level 3 queries: BatchGetQueryExecution
     * level 4 queries: StartQueryExecution, StopQueryExecution
     * level 5 queries: GetQueryExecution, GetQueryResults
     * DDL queries: create table, create table add partition
     * DML queries: select, create table as (CTAS)
     *
     * @param \Aws\Athena\AthenaClient $athenaClient
     * @param integer $level1QueriesMaxCalls max calls per second -> BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions
     * @param integer $level2QueriesMaxCalls max calls per second -> CreateNamedQuery, DeleteNamedQuery, GetNamedQuery
     * @param integer $level3QueriesMaxCalls max calls per second -> BatchGetQueryExecution
     * @param integer $level4QueriesMaxCalls max calls per second -> StartQueryExecution, StopQueryExecution
     * @param integer $level5QueriesMaxCalls max calls per second -> GetQueryExecution, GetQueryResults
     * @param integer $level1QueriesBurstCapacity burst capacity -> BatchGetNamedQuery, ListNamedQueries, ListQueryExecutions
     * @param integer $level2QueriesBurstCapacity burst capacity -> CreateNamedQuery, DeleteNamedQuery, GetNamedQuery
     * @param integer $level3QueriesBurstCapacity burst capacity -> BatchGetQueryExecution
     * @param integer $level4QueriesBurstCapacity burst capacity -> StartQueryExecution, StopQueryExecution
     * @param integer $level5QueriesBurstCapacity burst capacity -> GetQueryExecution, GetQueryResults
     * @param integer $ddlQueriesSimultaneous max simultaneous DDL queries -> create table, create table add partition
     * @param integer $dmlQueriesSimultaneous max simultaneous DML queries -> select, create table as (CTAS)
     */
    public function __construct(
        \Aws\Athena\AthenaClient $athenaClient,
        int $level1QueriesMaxCalls = self::AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL1QUERIES,
        int $level2QueriesMaxCalls = self::AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL2QUERIES,
        int $level3QueriesMaxCalls = self::AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL3QUERIES,
        int $level4QueriesMaxCalls = self::AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL4QUERIES,
        int $level5QueriesMaxCalls = self::AWS_DEFAULT_MAX_CALLS_PER_SECOND_LEVEL5QUERIES,
        int $level1QueriesBurstCapacity = self::AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL1QUERIES,
        int $level2QueriesBurstCapacity = self::AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL2QUERIES,
        int $level3QueriesBurstCapacity = self::AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL3QUERIES,
        int $level4QueriesBurstCapacity = self::AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL4QUERIES,
        int $level5QueriesBurstCapacity = self::AWS_DEFAULT_MAX_BURST_CAPACITY_LEVEL5QUERIES,
        int $ddlQueriesSimultaneous = self::AWS_DEFAULT_SIMULTANEOUS_DDL_QUERIES,
        int $dmlQueriesSimultaneous = self::AWS_DEFAULT_SIMULTANEOUS_DML_QUERIES)
    {
        $this->level1QueriesMaxCalls = max(1, $level1QueriesMaxCalls);
        $this->level2QueriesMaxCalls = max(1, $level2QueriesMaxCalls);
        $this->level3QueriesMaxCalls = max(1, $level3QueriesMaxCalls);
        $this->level4QueriesMaxCalls = max(1, $level4QueriesMaxCalls);
        $this->level5QueriesMaxCalls = max(1, $level5QueriesMaxCalls);

        $this->level1QueriesBurstCapacity = max(1, $level1QueriesBurstCapacity);
        $this->level2QueriesBurstCapacity = max(1, $level2QueriesBurstCapacity);
        $this->level3QueriesBurstCapacity = max(1, $level3QueriesBurstCapacity);
        $this->level4QueriesBurstCapacity = max(1, $level4QueriesBurstCapacity);
        $this->level5QueriesBurstCapacity = max(1, $level5QueriesBurstCapacity);

        $this->ddlQueriesSimultaneous = max(1, $ddlQueriesSimultaneous);
        $this->dmlQueriesSimultaneous = max(1, $dmlQueriesSimultaneous);

        $this->athenaClient = $athenaClient;
    }

    /**
     * Constructor for default values except simultaneous DDL queries
     * DDL queries: create table, create table add partition
     *
     * @param \Aws\Athena\AthenaClient $athenaClient
     * @param integer $ddlQueriesSimultaneous
     * @return \FC\AWS\Athena
     */
    public static function newDefaultWithDDL(\Aws\Athena\AthenaClient $athenaClient, int $ddlQueriesSimultaneous): \FC\AWS\Athena
    {
        $instance = new self($athenaClient);
        $instance->ddlQueriesSimultaneous = $ddlQueriesSimultaneous;

        return $instance;
    }

    /**
     * Constructor for default values except simultaneous DML queries
     * DML queries: select, create table as (CTAS)
     *
     * @param \Aws\Athena\AthenaClient $athenaClient
     * @param integer $dmlQueriesSimultaneous
     * @return \FC\AWS\Athena
     */
    public static function newDefaultWithDML(\Aws\Athena\AthenaClient $athenaClient, int $dmlQueriesSimultaneous): \FC\AWS\Athena
    {
        $instance = new self($athenaClient);
        $instance->dmlQueriesSimultaneous = $dmlQueriesSimultaneous;

        return $instance;
    }

    /**
     * Execute a query script
     *
     * @param string $queryScript the query script (UTF-8 formatted)
     * @param string $queryOutputLocation the output location on S3 for the query result
     * @param string $catalogName name of the source catalog
     * @param string $workgroup name of the workgroup
     * @return string ID of the query started
     */
    public function startQuery(string $queryScript, string $queryOutputLocation, string $catalogName, string $workgroup): string
    {
        // verify if query string max length not exceeded
        $queryLength = strlen($queryScript);
        if ($queryLength > self::QUERY_MAX_LENGTH) {
            throw new \Exception(sprintf("Query string max length exceeded: max length = %s, current query length = %s", number_format(self::QUERY_MAX_LENGTH), number_format($queryLength)));
        }

        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level4QueriesMaxCalls));

        // start query execution
        $query = $this->athenaClient->StartQueryExecution([
            'QueryString' => $queryScript,
            'ResultConfiguration' => [
                'OutputLocation' => $queryOutputLocation
            ],
            'QueryExecutionContext' => [
                'Catalog' => $catalogName
            ],
            'WorkGroup' => $workgroup
        ]);

        $execId = $query->get('QueryExecutionId');
        $this->queries[] = [ 'execId' => $execId, 'state' => self::QUERY_STATE_RUNNING ];

        return $execId;
    }

    /**
     * Get the current state of a query
     *
     * @param string $queryId query ID
     * @param string|null $executionTime readable execution time
     * @param string|null $reason reason of failure
     * @return string query state (RUNNING, QUEUED, SUCCEEDED, FAILED or CANCELLED)
     */
    public function getQueryCurrentState(string $queryId, ?string &$executionTime = null, ?string &$reason = null): string
    {
        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level5QueriesMaxCalls));

        $e = $this->athenaClient->getQueryExecution([ 'QueryExecutionId' => $queryId ]);

        if ($e['QueryExecution']['Status']['State'] === self::QUERY_STATE_FAILED && isset($e['QueryExecution']['Status']['StateChangeReason'])) {
            $reason = $e['QueryExecution']['Status']['StateChangeReason'];
        }

        if (isset($e['QueryExecution']['Statistics']['EngineExecutionTimeInMillis'])) {
            $executionTimeInSeconds = intval(intval($e['QueryExecution']['Statistics']['EngineExecutionTimeInMillis']) / 1000);
            $executionTime = gmdate("H:i:s", $executionTimeInSeconds);
        }

        return $e['QueryExecution']['Status']['State'];
    }

    /**
     * Check if the limit set for simultaneous running queries has been reached
     *
     * @return boolean true if reached, false otherwise
     */
    public function isQueryLimitReached(string $queryType): bool
    {
        // queryType must be DDL or DML (see class constants)
        $limit = match ($queryType) {
            self::QUERY_TYPE_DDL => $this->ddlQueriesSimultaneous,
            self::QUERY_TYPE_DML => $this->dmlQueriesSimultaneous,
            default => throw new \Exception(sprintf("Query type must be either of these: %s or %s, %s given", self::QUERY_TYPE_DDL, self::QUERY_TYPE_DML, $queryType)),
        };

        $this->updateQueriesState();

        $running = 0;
        foreach (array_column($this->queries, 'state') as $state) {
            if (!in_array($state, self::QUERY_STOP_STATES, true)) {
                $running++;
            }
        }
        return ($running >= $limit);
    }

    /**
     * Check if there are running queries
     *
     * @return boolean true if at least one query is still running, false otherwise
     */
    public function existRunningQuery(): bool
    {
        $this->updateQueriesState();

        foreach (array_column($this->queries, 'state') as $state) {
            if (!in_array($state, self::QUERY_STOP_STATES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the queries state
     *
     * @return void
     */
    private function updateQueriesState(): void
    {
        foreach ($this->queries as &$query) {
            if (!in_array($query['state'], self::QUERY_STOP_STATES, true)) {
                $query['state'] = $this->getQueryCurrentState($query['execId']);
            }
        }
        unset($query);
    }

    /**
     * Stop a query
     * State must be confirmed using getQueryCurrentState
     *
     * @param string $queryId query ID
     * @return void
     */
    public function StopQuery(string $queryId): void
    {
        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level4QueriesMaxCalls));

        $this->athenaClient->stopQueryExecution([ 'QueryExecutionId' => $queryId ]);
        $key = array_search($queryId, array_column($this->queries, 'execId'), true);
        if ($key !== false) {
            $this->queries[$key]['state'] = self::QUERY_STATE_CANCELLED;
        }
    }

    /**
     * Stop all queries
     *
     * @return void
     */
    public function StopAllQueries(): void
    {
        foreach ($this->queries as &$query) {
            if (!in_array($query['state'], self::QUERY_STOP_STATES, true)) {
                // get current query state
                $query['state'] = $this->getQueryCurrentState($query['execId']);

                if (!in_array($query['state'], self::QUERY_STOP_STATES, true)) {
                    // pause execution to stay under the limits
                    usleep(intval(1000000 / $this->level4QueriesMaxCalls));

                    $this->athenaClient->stopQueryExecution([ 'QueryExecutionId' => $query['execId'] ]);
                    $query['state'] = self::QUERY_STATE_CANCELLED;
                }
            }
        }
        unset($query);
    }

    /**
     * Create a named query
     *
     * @param string $queryScript the query script (UTF-8 formatted)
     * @param string $database database to which the query belong
     * @param string $queryName name for the query
     * @param string $queryDescription description for the query
     * @return string named query id
     */
    public function createNamedQuery(string $queryScript, string $database, string $queryName, string $queryDescription = ''): string
    {
        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level2QueriesMaxCalls));

        $r = $this->athenaClient->createNamedQuery([
            'QueryString' => $queryScript,
            'Database' => $database,
            'Name' => $queryName,
            'Description' => $queryDescription
        ]);

        if (!isset($r['NamedQueryId'])) {
            throw new \Exception("Failed to create named query: " . self::getErrorMessage($r));
        }

        return $r['NamedQueryId'];
    }

    /**
     * Delete a named query
     *
     * @param string $namedQueryId Id of the named query
     * @return void
     */
    public function deleteNamedQuery(string $namedQueryId): void
    {
        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level2QueriesMaxCalls));

        $r = $this->athenaClient->deleteNamedQuery([
            'NamedQueryId' => $namedQueryId
        ]);

        if (is_array($r) && count((array)$r) !== 0) {
            throw new \Exception("Failed to delete named query: " . self::getErrorMessage($r));
        }
    }

    /**
     * Retrieve details of a named query
     *
     * @param string $namedQueryId Id of the named query
     * @return array associative array with database, description, name, id, query script, workgroup
     */
    public function getNamedQueryDetails(string $namedQueryId): array
    {
        // pause execution to stay under the limits
        usleep(intval(1000000 / $this->level2QueriesMaxCalls));

        $r = $this->athenaClient->getNamedQuery([
            'NamedQueryId' => $namedQueryId
        ]);

        if (!isset($r['NamedQuery'])) {
            throw new \Exception("Failed to retrieve named query details: " . self::getErrorMessage($r));
        }

        return (array)$r['NamedQuery'];
    }

    /**
     * List all named queries
     *
     * @return array
     */
    public function listNamedQueries(string &$nextPaginationToken): array
    {
        $namedQueriesList = [];

        if ($nextPaginationToken !== '') {
            // pause execution to stay under the limits
            // usleep(intval(1000000 / $this->level1QueriesMaxCalls));
            sleep(10); // IMPROVE: forced 10 second wait between calls to avoid max rate exceeded error

            $r = $this->athenaClient->listNamedQueries([
                'NextToken' => $nextPaginationToken
            ]);
        } else {
            $r = $this->athenaClient->listNamedQueries();
        }

        if (!isset($r['NamedQueryIds'])) {
            throw new \Exception("Failed to retrieve named queries list: " . self::getErrorMessage($r));
        } else {
            $namedQueriesList = array_merge($namedQueriesList, $r['NamedQueryIds']);
        }

        $nextPaginationToken = $r['NextToken'] ?? '';

        return $namedQueriesList;
    }

    /**
     * List all databases inside a source catalog
     *
     * @param string $catalogName name of the source catalog
        * @return array
     */
    public function listAllDatabases(string $catalogName): array
    {
        $nextPaginationToken = '';
        $databaseList = [];

        do {
            if ($nextPaginationToken !== '') {
                $r = $this->athenaClient->listDatabases([
                    'CatalogName' => $catalogName,
                    'NextToken' => $nextPaginationToken
                ]);
            } else {
                $r = $this->athenaClient->listDatabases([
                    'CatalogName' => $catalogName
                ]);
            }

            if (!isset($r['DatabaseList'])) {
                throw new \Exception("Failed to retrieve database list: " . self::getErrorMessage($r));
            } else {
                foreach($r['DatabaseList'] as $databaseDetails) {
                    if (isset($databaseDetails['Name'])) { $databaseList[] = $databaseDetails['Name']; }
                }
            }

            $nextPaginationToken = $r['NextToken'] ?? '';
        } while ($nextPaginationToken !== '');

        return $databaseList;
    }

    /**
     * Retrieve database details
     *
     * @param string $database database name
     * @param string $catalogName name of the source catalog
     * @return array database details
     */
    public function getDatabaseDetails(string $database, string $catalogName): array
    {
        $r = $this->athenaClient->getDatabase([
            'CatalogName' => $catalogName,
            'DatabaseName' => $database
        ]);

        if (!isset($r['Database'])) {
            throw new \Exception("Failed to retrieve database details: " . self::getErrorMessage($r));
        }

        return (array)($r['Database']);
    }

    /**
     * Retrieve database tables details
     *
     * @param string $database database name
     * @param string $catalogName name of the source catalog
     * @param string $tableNameRegex pattern-matching expression for table names to include (empty for all tables)
     * @return array tables details
     */
    public function listAllTablesDetails(string $database, string $catalogName, string $tableNameRegex = ''): array
    {
        $nextPaginationToken = '';
        $tablesDetails = [];

        do {
            if ($nextPaginationToken !== '') {
                $r = $this->athenaClient->listTableMetadata([
                    'CatalogName' => $catalogName,
                    'DatabaseName' => $database,
                    'Expression' => $tableNameRegex,
                    'NextToken' => $nextPaginationToken
                ]);
            } else {
                $r = $this->athenaClient->listTableMetadata([
                    'CatalogName' => $catalogName,
                    'DatabaseName' => $database,
                    'Expression' => $tableNameRegex
                ]);
            }

            if (!isset($r['TableMetadataList'])) {
                throw new \Exception("Failed to retrieve tables details list: " . self::getErrorMessage($r));
            } else {
                $tablesDetails = array_merge($tablesDetails, $r['TableMetadataList']);
            }

            $nextPaginationToken = $r['NextToken'] ?? '';
        } while ($nextPaginationToken !== '');

        return $tablesDetails;
    }

    /**
     * Retrieve table details
     *
     * @param string $database database name
     * @param string $table table name
     * @param string $catalogName name of the source catalog
        * @return array
     */
    public function getTableDetails(string $database, string $table, string $catalogName): array
    {
        $r = $this->athenaClient->getTableMetadata([
            'CatalogName' => $catalogName,
            'DatabaseName' => $database,
            'TableName' => $table
        ]);

        if (!isset($r['TableMetadata'])) {
            throw new \Exception("Failed to retrieve table details: " . self::getErrorMessage($r));
        }

        return (array)($r['TableMetadata']);
    }

    /**
     * Retrieve error message from results array
     *
     * @param mixed $results array-like result containing details from previous API call
     * @return string error message
     */
    private static function getErrorMessage(mixed $results): string
    {
        if (is_array($results) || $results instanceof \ArrayAccess) {
            return $results['Message'] ?? 'no error detail';
        }

        return 'no error detail';
    }
}

