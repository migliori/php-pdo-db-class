<?php

declare(strict_types=1);

namespace database;

use PDO;

/**
 * DB class - PDO Database abstraction layer class
 *
 * The DB class is a database abstraction layer that provides a simple, consistent interface
 * for interacting with different types of databases. It handles connection management, query execution,
 * pagination and result processing, allowing developers to focus on the business logic of their application.
 *
 * Full documentation with code examples is available here: {@link [https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php] [https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php]}
 *
 * The DB class is designed to be flexible and extensible, allowing developers to easily customize it
 * to meet their specific needs. It supports multiple database types, including MySQL, PostgreSQL, Firebird,
 * and Oracle, and can be easily extended to support additional databases.

 * The DB class is designed to be easy to use and understand. It provides a set of simple, intuitive methods
 * for executing queries and retrieving data, and it automatically handles error handling and debugging.
 * This makes it easy for developers to quickly get up and running with the class, without having to worry
 * about low-level details such as database connections and query execution.

 * In addition, the DB class is designed to be highly efficient and fast. It uses the latest database features
 * and optimization techniques to ensure that queries are executed quickly and efficiently, without sacrificing
 * performance. This means that applications built using the DB class can scale easily and perform well under
 * load, even with large amounts of data.
 *
 * @api
 * @author Gilles Migliori
 * @version 2.2
 * @license GNU General Public License v3.0
 * @link https://github.com/gilles-migliori/php-pdo-db-class
 * @link https://packagist.org/packages/gilles-migliori/php-pdo-db-class
 * @link https://www.phpformbuilder.pro/documentation/db-help.php
 */
class DB
{
    public bool $show_errors;
    private string $debug_content = '';
    /**
     * if $debug_mode === 'register' the debug content is available with $this->getDebugContent();
     */
    private string $debug_mode = 'echo'; // 'echo' or 'register'
    private bool $driver_supports_last_insert_id;
    private string $error = ''; // error message if any failure
    private string|false $last_insert_id = false;
    private string $num_rows_query_string = "'*'"; // query string for numRows()'s SELECT COUNT()
    private ?PDO $pdo; // PDO internal object
    private string $pdo_driver;
    private ?\PDOStatement $query = null; // PDO Statement
    private int|bool $row_count = 0; // number of rows returned by the latest query
    private string $where_clause_sql = ''; // where clause SQL
    /**
     * @var array<string, string|int|bool|\DateTime|null> $where_clause_placeholders where clause placeholders
     */
    private array $where_clause_placeholders = [];

    /**
     * Constructor
     *
     * @param bool $show_errors [OPTIONAL] If set to true, will output errors
     * @param string $driver [OPTIONAL] Database driver
     * @param string $hostname [OPTIONAL] Database hostname
     * @param string $database [OPTIONAL] Database name
     * @param string $username [OPTIONAL] Database username
     * @param string $password [OPTIONAL] Database password
     * @param string $port [OPTIONAL] Database port
     */
    public function __construct(
        bool $show_errors = false,
        string $driver = PDO_DRIVER,
        string $hostname = DB_HOST,
        string $database = DB_NAME,
        string $username = DB_USER,
        string $password = DB_PASS,
        string $port = DB_PORT
    ) {
        $this->pdo_driver  = $driver;
        $this->show_errors = $show_errors;

        $this->driver_supports_last_insert_id = false;
        if ($driver == 'mysql') {
            $this->driver_supports_last_insert_id = true;
        }

        try {
            if (!empty($port)) {
                if ($driver === 'oci') {
                    $port = ':' . $port;
                } else {
                    $port = 'port=' . $port . ';';
                }
            }
            switch ($driver) {
                case 'firebird':
                    $dsn = 'firebird:dbname='   . $hostname . ':' . $database . ';charset=utf-8';
                    break;

                case 'mysql':
                    $dsn = 'mysql:host='   . $hostname . ';' . $port . 'dbname=' . $database . ';charset=utf8';
                    break;

                case 'oci':
                    $dsn = 'oci:dbname='   . $hostname . $port . '/' . $database . ';charset=utf8';
                    break;

                case 'pgsql':
                    $dsn = 'pgsql:host='   . $hostname . ';' . $port . 'dbname=' . $database . ';options=\'--client_encoding=UTF8\'';
                    break;

                default:
                    $dsn = '';
                    break;
            }

            $this->pdo = new \PDO(
                $dsn,
                $username,
                $password
            );

            // If we are connected...
            if ($this->isConnected() && $this->show_errors) {
                // The default error mode for PDO is \PDO::ERRMODE_SILENT.
                // With this setting left unchanged, you'll need to manually
                // fetch errors, after performing a query
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }
            if ($driver === 'firebird') {
                // Force column names uppercase
                $this->pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_UPPER);
            }
            if ($driver === 'oci') {
                // standardize the OCI date format
                $sql = 'ALTER SESSION SET NLS_DATE_FORMAT = \'YYYY-MM-DD\'';
                $this->execute($sql);
            }
        } catch (\PDOException $e) {
            // If connection was not successful
            $error = 'Database Connection Error (' . __METHOD__ . '): ' .
                mb_convert_encoding($e->getMessage(), "UTF-8", mb_detect_encoding($e->getMessage())) . '<br>' . $dsn;

            // Send the error to the error event handler
            $this->errorEvent($error, $e->getCode());
        }
    }

    /**
     * Executes a SQL statement using PDO
     *
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Associative array placeholders for binding to SQL
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return bool|string true if success, otherwise false, or last_insert_id if an INSERT statement
     * @throws \PDOException if there was an error executing the query
     */
    public function execute(string $sql, array $placeholders = [], bool $debug = false): mixed
    {
        // remove the line breaks
        $sql = \str_replace(["\r", "\n"], '', $sql);

        // Set the variable initial values
        $time  = false;

        // reset the global db_row_count
        $this->row_count = 0;

        // Is there already a transaction pending? No nested transactions in MySQL!
        $existing_transaction = $this->pdo ? $this->pdo->inTransaction() : false;

        // Is this a SQL SELECT statement? Check the first word...
        $is_select = false;
        $is_delete = false;
        $is_insert = false;
        if ($token = strtok(trim($sql), ' ')) {
            $is_select = (\strtoupper($token) === 'SELECT');
            $is_delete = (\strtoupper($token) === 'DELETE');
            $is_insert = (\strtoupper($token) === 'INSERT');
        }

        // Set a flag
        $return = false;

        try {
            $is_sql_auto_commit = $this->isSqlAutoCommit($sql);

            if (!$this->pdo instanceof \PDO) {
                throw new \PDOException('No database connection');
            } else {

                // Begin a transaction
                if (!$existing_transaction && !$is_sql_auto_commit) {
                    $this->pdo->beginTransaction();
                }

                // Create the query object
                $this->query = $this->pdo->prepare($sql);

                // If there are values in the passed in array
                if (!empty($placeholders)) {
                    // Loop through the placeholders and values
                    foreach ($placeholders as $field => $value) {
                        // Determine the datatype
                        if (\is_int($value)) {
                            $datatype = \PDO::PARAM_INT;
                        } elseif (\is_bool($value)) {
                            $datatype = \PDO::PARAM_BOOL;
                        } elseif (\is_null($value)) {
                            $datatype = \PDO::PARAM_NULL;
                        } elseif ($value instanceof \DateTime) {
                            $value = $value->format('Y-m-d H:i:s');
                            $placeholders[$field] = $value;
                            $datatype = \PDO::PARAM_STR;
                        } else {
                            $datatype = \PDO::PARAM_STR;
                        }

                        // Bind the placeholder and value to the query
                        $this->query->bindValue($field, $value, $datatype);
                    }
                }

                // Start a timer
                $time_start = microtime(true);

                // Execute the query
                $this->query->execute();

                $return = true;
                if ($is_insert && $this->driver_supports_last_insert_id) {
                    $this->last_insert_id = $this->pdo->lastInsertId();
                    $return = $this->last_insert_id;
                }

                // Find out how long the query took
                $time_end = microtime(true);
                $time = $time_end - $time_start;

                if ($is_select) {
                    $this->row_count = $this->numRows($placeholders, $debug);
                } elseif ($is_delete || $is_insert) {
                    $this->row_count = $this->query->rowCount();
                    if ($this->row_count < 1) {
                        // return false but don't throw any error if a deletion fails
                        $return = false;
                        if ($is_insert) {
                            $msg = 'Failed to insert record(s)';
                            throw new \PDOException($msg);
                        }
                    }
                }

                // Debug only
                if ($debug) {
                    // Rollback the transaction
                    if (!$existing_transaction && !$is_sql_auto_commit) {
                        $this->pdo->rollback();
                    }

                    $interpolated_sql = $this->interpolateQuery($sql, $placeholders);
                    // Output debug information
                    $this->dumpDebug(
                        __FUNCTION__,
                        $interpolated_sql,
                        $placeholders,
                        $this->query,
                        false,
                        $time,
                        $this->row_count
                    );
                } elseif (!$existing_transaction && !$is_sql_auto_commit) {
                    // Commit the transaction
                    $this->pdo->commit();
                }
            }
        } catch (\PDOException $e) { // If there was an error...
            $return = false;
            $interpolated_sql = $this->interpolateQuery($sql, $placeholders);
            // Get the error
            $err = 'Database Error (' . __METHOD__ . '):<br>'
                . $e->getMessage() . '<br>' . $interpolated_sql;

            // Send the error to the error event handler
            $this->errorEvent($err, $e->getCode());

            // If we are in debug mode...
            if ($debug) {
                // Output debug information
                $this->dumpDebug(
                    __FUNCTION__,
                    $sql,
                    $placeholders,
                    $this->query,
                    false,
                    $time,
                    $this->row_count,
                    $err
                );
            }

            // Rollback the transaction
            if (!$existing_transaction && !$is_sql_auto_commit && $this->pdo instanceof \PDO) {
                $this->pdo->rollback();
            }
        } catch (\Exception $e) { // If there was an error...
            $return = false;
            // Get the error
            $err = 'General Error (' . __METHOD__ . '): ' . $e->getMessage();

            // Send the error to the error event handler
            $this->errorEvent($err, $e->getCode());

            // If we are in debug mode...
            if ($debug) {
                // Output debug information
                $this->dumpDebug(
                    __FUNCTION__,
                    $sql,
                    $placeholders,
                    $this->query,
                    false,
                    $time,
                    $this->row_count,
                    $err
                );
            }

            // Rollback the transaction
            if (!$existing_transaction && isset($is_sql_auto_commit) && !$is_sql_auto_commit && $this->pdo instanceof \PDO) {
                $this->pdo->rollback();
            }
        }

        // Clean up
        unset($this->query);

        // Return true if success and false if failure
        return $return;
    }

    /**
     * Executes a SQL query using PDO
     *
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Associative array placeholders for binding to SQL
     *                            array('name' => 'Cathy', 'city' => 'Cardiff')
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return bool true if success otherwise false
     * @throws \PDOException if there was a database error
     * @throws \Exception if there was a general error
     */
    public function query(
        string $sql,
        array $placeholders = [],
        bool $debug = false
    ): bool {
        // Set the variable initial values
        $this->query = null;
        $return = false;
        $time = false;

        // reset the global db_row_count
        $this->row_count = 0;

        // remove the line breaks
        $sql = \str_replace(["\r", "\n"], '', $sql);

        try {
            if (!$this->pdo instanceof \PDO) {
                throw new \PDOException('No database connection');
            }

            // Create the query object
            $this->query = $this->pdo->prepare($sql);

            // If there are values in the passed in array
            if (!empty($placeholders)) {
                // Loop through the placeholders and values
                foreach ($placeholders as $field => $value) {
                    // Determine the datatype
                    if (is_int($value)) {
                        $datatype = \PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $datatype = \PDO::PARAM_BOOL;
                    } elseif (is_null($value)) {
                        $datatype = \PDO::PARAM_NULL;
                    } elseif ($value instanceof \DateTime) {
                        $value = $value->format('Y-m-d H:i:s');
                        $placeholders[$field] = $value;
                        $datatype = \PDO::PARAM_STR;
                    } else {
                        $datatype = \PDO::PARAM_STR;
                    }

                    // Bind the placeholder and value to the query
                    $this->query->bindValue($field, $value, $datatype);
                }
            }

            // Start a timer
            $time_start = microtime(true);

            // Execute the query
            $this->query->execute();

            // Find out how long the query took
            $time_end = microtime(true);
            $time = $time_end - $time_start;

            $this->row_count = $this->numRows($placeholders, $debug);

            // Query was successful
            $return = true;

            // Debug only
            if ($debug) {
                $interpolated_sql = $this->interpolateQuery($sql, $placeholders);
                // Output debug information
                $this->dumpDebug(
                    __FUNCTION__,
                    $interpolated_sql,
                    $placeholders,
                    $this->query,
                    $return,
                    $time,
                    $this->row_count
                );
            }
        } catch (\PDOException $e) { // If there was a database error...
            $interpolated_sql = $this->interpolateQuery($sql, $placeholders);
            // Get the error
            $err = 'Database Error (' . __METHOD__ . '):<br>'
                . $e->getMessage() . '<br>' . $interpolated_sql;

            // Send the error to the error event handler
            $this->errorEvent($err, $e->getCode());

            // If we are in debug mode...
            if ($debug) {
                // Output debug information
                $this->dumpDebug(
                    __FUNCTION__,
                    $sql,
                    $placeholders,
                    $this->query,
                    $return,
                    $time,
                    false,
                    $err
                );
            }

            throw $e;
        } catch (\Exception $e) { // If there was a general error...
            // Get the error
            $err = 'General Error (' . __METHOD__ . '): ' . $e->getMessage();

            // Send the error to the error event handler
            $this->errorEvent($err, $e->getCode());

            // If we are in debug mode...
            if ($debug) {
                // Output debug information
                $this->dumpDebug(
                    __FUNCTION__,
                    $sql,
                    $placeholders,
                    $this->query,
                    $return,
                    $time,
                    false,
                    $err
                );
            }

            throw $e;
        }

        // Return true if success and false if failure
        return $return;
    }

    /**
     * Executes a SQL query using PDO and returns one row
     *
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Associative array placeholders for binding to SQL
     *                          e.g.:array('name' => 'Cathy', 'city' => 'Cardiff')
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @param int $fetch_parameters [OPTIONAL] PDO fetch style record options
     * @return mixed Array or object with values if success otherwise false
     */
    public function queryRow(
        string $sql,
        array $placeholders = [],
        bool $debug = false,
        int $fetch_parameters = \PDO::FETCH_OBJ
    ): mixed {
        // It's better on resources to add LIMIT 1 to the end of your SQL
        // statement if there are multiple rows that will be returned
        $this->query($sql, $placeholders, $debug);

        return $this->fetch($fetch_parameters);
    }

    /**
     * Executes a SQL query using PDO and returns a single value only
     *
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Associative array placeholders for binding to SQL
     *                          e.g.: array('name' => 'Cathy', 'city' => 'Cardiff')
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return mixed A returned value from the database if success otherwise false
     */
    public function queryValue(
        string $sql,
        array $placeholders = [],
        bool $debug = false
    ): mixed {
        // It's better on resources to add LIMIT 1 to the end of your SQL
        // if there are multiple rows that will be returned
        $results = $this->queryRow($sql, $placeholders, $debug, \PDO::FETCH_NUM);

        // If a record was returned
        if (is_array($results)) {
            // Return the first element of the array which is the first row
            return $results[0];
        } else {
            // No records were returned
            return false;
        }
    }

    /**
     * Selects records using PDO
     *
     * @param string                                             $from       Table name with the possible joins
     * @param string|array<string>                               $values     [OPTIONAL] Array or string containing the field names
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where      [OPTIONAL] Array containing the fields and values or a string
     * @param array<string, bool|null|int|string|array<string>>  $extras     [OPTIONAL] Array containing the optional following pairs of key => values:
     *                                                  - 'select_distinct' => If set to true the query will use 'SELECT DISTINCT' instead of 'SELECT'. Default is false.
     *                                                  - 'order_by' => Array or string containing field(s) order,
     *                                                     or null to not specify any order. Default is null.
     *                                                  - 'group_by' => Array or string containing field(s) for group. Default is null.
     *                                                  - 'limit' => Integer or string containing the maximum number of results,
     *                                                     or null to not specify any limit. E.g: 'limit' => 10 or 'limit' => '10, 20'.
     * @param bool                                               $debug      [OPTIONAL] If set to true, will output results and query info
     *
     * @return mixed true if success otherwise false
     */
    public function select(
        string $from,
        mixed $values = '*',
        mixed $where = [],
        array $extras = [],
        bool $debug = false
    ): mixed {
        $default_extras = [
            'select_distinct' => false,
            'order_by' => null,
            'group_by' => null,
            'limit' => null
        ];

        $extras = array_merge($default_extras, $extras);

        $sql = [
            'select' => '',
            'distinct' => '',
            'values' => '',
            'from' => '',
            'where' => '',
            'order_by' => '',
            'group_by' => '',
            'limit' => ''
        ];

        $sql['select'] = 'SELECT ';

        // If the values are in an array
        if (is_array($values)) {
            // Join the fields
            $sql['values'] = implode(', ', $values);
        } else { // It's a string
            // Create the SELECT
            $sql['values'] = trim($values);
        }

        $this->num_rows_query_string = '\'*\'';

        if ($extras['select_distinct']) {
            $sql['distinct'] .= 'DISTINCT ';
            // register the COUNT(DISTINCT) values for numRows
            if (is_string($extras['select_distinct'])) {
                $this->num_rows_query_string = 'DISTINCT ' . $extras['select_distinct'];
            }
        }

        $sql['from'] = ' FROM ' . trim($from);

        // Create the SQL WHERE clause
        if (is_array($where)) {
            $where = array_filter($where);
        }

        $this->whereClause($where);

        if (!empty($this->where_clause_sql)) {
            $sql['where'] = $this->where_clause_sql;
        }

        if (!is_null($extras['order_by'])) {
            // If the order values are in an array
            if (is_array($extras['order_by'])) {
                // Join the fields
                $sql['order_by'] = ' ORDER BY ' . implode(', ', $extras['order_by']);
            } else { // It's a string
                // Specify the order
                $sql['order_by'] = ' ORDER BY ' . trim((string) $extras['order_by']);
            }
        }

        if (!is_null($extras['group_by'])) {
            // If the group values are in an array
            if (is_array($extras['group_by'])) {
                // Join the fields
                $sql['group_by'] = ' GROUP BY ' . implode(', ', $extras['group_by']);
            } else { // It's a string
                // Specify the group
                $sql['group_by'] = ' GROUP BY ' . trim((string) $extras['group_by']);
            }
        }

        if (is_int($extras['limit']) || \is_string($extras['limit'])) {
            // Specify the limit
            $sql['limit'] = $this->getLimitSqlFromDriver($extras['limit']);
        }

        if ($this->pdo_driver == 'firebird') {
            $final_sql = $sql['select'] . $sql['limit'] . $sql['distinct'] . $sql['values'] . $sql['from'] . $sql['where'] . $sql['group_by'] . $sql['order_by'];
        } else {
            $final_sql = $sql['select'] . $sql['distinct'] . $sql['values'] . $sql['from'] . $sql['where'] . $sql['group_by'] . $sql['order_by'] . $sql['limit'];
        }

        // Execute the query and return the results
        return $this->query(
            $final_sql,
            $this->where_clause_placeholders,
            $debug
        );
    }

    /**
     * Select COUNT records using PDO
     *
     * @param string $from Table name with the possible joins
     * @param array<string, string>|string $values [OPTIONAL] Array of fieldnames => aliases to be returned.
     *                             Default will count the number of records and return it with the rows_count alias.
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where [OPTIONAL] Array containing the fields and values or a string
     *                            $where = array();
     *                            $where['id >'] = 1234;
     *                            $where[] = 'first_name IS NOT NULL';
     *                            $where['some_value <>'] = 'text';
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return mixed The record row or false if no record has been found
     */
    public function selectCount(
        string $from,
        mixed $values = ['*' => 'rows_count'],
        mixed $where = [],
        bool $debug = false
    ): mixed {
        $count_values = [];
        // If the values are in an array
        if (is_array($values)) {
            // Build the COUNT queries with aliases
            foreach ($values as $key => $value) {
                $count_values[] = 'COUNT(' . $key . ') AS ' . $value;
            }
        } else { // It's a string
            // field AS f, field2 AS f2, DISTINCT fielf3 AS f3
            $str_values = explode(', ', $values);
            foreach ($str_values as $v) {
                $count_values[] = 'COUNT(' . str_replace(' AS ', ') AS ', $v);
            }
        }

        $this->select(
            $from,
            $count_values,
            $where,
            [],
            $debug
        );

        return $this->fetch();
    }

    /**
     * Selects a single record using PDO
     *
     * @param string $from Table name with the possible joins
     * @param string|array<string> $values [OPTIONAL] Array or string containing the field names
     *                             array('name', 'city') or 'name, city'
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where [OPTIONAL] Array containing the fields and values or a string
     *                            $where = array();
     *                            $where['id >'] = 1234;
     *                            $where[] = 'first_name IS NOT NULL';
     *                            $where['some_value <>'] = 'text';
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @param int $fetch_parameters [OPTIONAL] PDO fetch style record options
     * @return mixed Array or object with values if success otherwise false
     */
    public function selectRow(
        string $from,
        mixed $values = '*',
        mixed $where = [],
        bool $debug = false,
        int $fetch_parameters = \PDO::FETCH_OBJ
    ): mixed {
        $sql = [
            'select' => '',
            'values' => '',
            'from' => '',
            'where' => '',
            'limit' => ''
        ];

        $sql['select'] = 'SELECT ';

        // If the values are in an array
        if (is_array($values)) {
            // Join the fields
            $sql['values'] = implode(', ', $values);
        } else { // It's a string
            // Create the SELECT
            $sql['values'] = trim($values);
        }

        $sql['from'] = ' FROM ' . trim($from);

        // Create the SQL WHERE clause
        if (is_array($where)) {
            $where = array_filter($where);
        }

        $this->whereClause($where);

        if (!empty($this->where_clause_sql)) {
            $sql['where'] = $this->where_clause_sql;
        }

        // Make sure only one row is returned
        $sql['limit'] = $this->getLimitSqlFromDriver(1);

        if ($this->pdo_driver == 'firebird') {
            $final_sql = $sql['select'] . $sql['limit'] . $sql['values'] . $sql['from'] . $sql['where'];
        } else {
            $final_sql = $sql['select'] . $sql['values'] . $sql['from'] . $sql['where'] . $sql['limit'];
        }

        // Execute the query and return the results
        return $this->queryRow(
            $final_sql,
            $this->where_clause_placeholders,
            $debug,
            $fetch_parameters
        );
    }

    /**
     * Selects a single value using PDO
     *
     * @param string $from Table name with the possible joins
     * @param string|array<string> $field [OPTIONAL] Array or string containing the field name
     *                                  e.g. array('name') or 'name'
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where [OPTIONAL] Array containing the fields and values or a string
     *                                   e.g. $where = array();
     *                                   $where['id >'] = 1234;
     *                                   $where[] = 'first_name IS NOT NULL';
     *                                   $where['some_value <>'] = 'text';
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return mixed A returned value from the database if success otherwise false
     */
    public function selectValue(
        string $from,
        mixed $field,
        mixed $where = [],
        bool $debug = false
    ): mixed {
        // Return the row
        // expects array<int|string, int<min, -1>|int<1, max>|string>|string, array<string, mixed>|string given.
        $results = $this->selectRow($from, $field, $where, $debug, \PDO::FETCH_NUM);

        // If a record was returned
        if (is_array($results)) {
            // Return the first element of the array which is the first row
            return $results[0];
        } else {
            // No records were returned
            return false;
        }
    }

    /**
     * Inserts a new record into a table using PDO
     *
     * @param string $table Table name
     * @param array<string, string|int|bool|\DateTime|null> $values Associative array containing the fields and values
     *                          e.g. ['name' => 'Cathy', 'city' => 'Cardiff']
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return mixed Returns the last inserted ID or true otherwise false
     */
    public function insert(
        string $table,
        array $values,
        bool $debug = false
    ): mixed {
        if (!is_array($values) || empty($values)) {
            $err = 'Failed to insert data into table "<em>' . $table . '</em>".<br>The array of values to be inserted cannot be empty.';
            $this->errorEvent($err);

            return false;
        } else {
            // Create the SQL statement with PDO placeholders created with regex
            $sql = 'INSERT INTO ' . trim($table) . ' ('
                . implode(', ', array_keys($values)) . ') VALUES ('
                . implode(', ', (array) preg_replace('/^([A-Za-z0-9_-]+)$/', ':${1}', array_keys($values)))
                . ')';

            // Execute the query
            return $this->execute($sql, $values, $debug);
        }
    }

    /**
     * Updates an existing record into a table using PDO
     *
     * @param string $table Table name
     * @param array<string, string|int|bool|\DateTime|null> $values Associative array containing the fields and values
     *                          e.g. ['name' => 'Cathy', 'city' => 'Cardiff']
     * @param string|array<int|string, int|string> $where [OPTIONAL] Array containing the fields and values or a string
     *                            $where = [];
     *                            $where['id >'] = 1234;
     *                            $where[] = 'first_name IS NOT NULL';
     *                            $where['some_value <>'] = 'text';
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return bool true if success otherwise false
     */
    public function update(
        string $table,
        array $values,
        mixed $where = [],
        bool $debug = false
    ): bool {
        // Create the initial SQL
        $sql = 'UPDATE ' . trim($table) . ' SET ';

        // Create SQL SET values
        $output = [];
        foreach ($values as $key => $value) {
            $output[] = $key . ' = :' . $key;
        }

        // Concatenate the array values
        $sql .= implode(', ', $output);

        // Create the SQL WHERE clause
        if (is_array($where)) {
            $where = array_filter($where);
        }

        $this->whereClause($where);

        if (!empty($this->where_clause_sql)) {
            $sql['where'] = $this->where_clause_sql;
        }

        $values = array_merge($values, $this->where_clause_placeholders);

        // Execute the query
        return (bool) $this->execute(
            $sql,
            $values,
            $debug
        );
    }

    /**
     * Deletes a record from a table using PDO
     *
     * @param string $from Table name with the possible joins
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where [OPTIONAL] Array containing the fields and values or a string
     *                            Example array: ['id >' => 1234, 'some_value <>' => 'text']
     *                            Example string: 'first_name IS NOT NULL'
     * @param bool $debug [OPTIONAL] If set to true, will output results and query info
     * @return bool True if success otherwise false
     */
    public function delete(
        string $from,
        mixed $where = [],
        bool $debug = false
    ): bool {
        // Create the SQL WHERE clause
        if (is_array($where)) {
            $where = array_filter($where);
        }

        $this->whereClause($where);

        switch ($this->pdo_driver) {
            case 'firebird':
                $sql = 'DELETE FROM ' . trim($from);
                // e.g.: 'film_actor LEFT JOIN film ON film_actor.film_id = film.film_id'
                if (preg_match('/([a-z_-]+) (?>INNER|LEFT|RIGHT) JOIN ([a-z_-]+) ON ([a-z_.-]+)\s*=\s*([a-z_.-]+)/i', $from, $out)) {
                    $sql = 'DELETE FROM ' . $out[1] . '
                    WHERE EXISTS (SELECT * FROM ' . $out[2] . ' WHERE ' . $out[4] . ' = ' . $out[3];
                    $sql .= str_ireplace('WHERE', 'AND', $this->where_clause_sql);
                    $sql .= ');';
                } else {
                    $sql .= $this->where_clause_sql;
                }
                break;

            case 'oci':
                $sql = 'DELETE FROM ' . trim($from);
                // e.g.: 'film_actor LEFT JOIN film ON film_actor.film_id = film.film_id'
                if (preg_match('/([a-z_-]+) (?>INNER|LEFT|RIGHT) JOIN ([a-z_-]+) ON ([a-z_.-]+)\s*=\s*([a-z_.-]+)/i', $from, $out)) {
                    $sql = 'DELETE ' . $out[1] . '
                    WHERE EXISTS (SELECT * FROM ' . $out[2] . ' WHERE ' . $out[4] . ' = ' . $out[3];
                    $sql .= str_ireplace('WHERE', 'AND', $this->where_clause_sql);
                    $sql .= ')';
                } else {
                    $sql .= $this->where_clause_sql;
                }
                break;

            case 'pgsql':
                $sql = 'DELETE FROM ' . trim($from);
                // e.g.: 'film_actor LEFT JOIN film ON film_actor.film_id = film.film_id'
                if (preg_match('/([a-z_-]+) (?>INNER|LEFT|RIGHT) JOIN ([a-z_-]+) ON ([a-z_.-]+)\s*=\s*([a-z_.-]+)/i', $from, $out)) {
                    $sql = 'DELETE FROM ' . $out[1] . ' USING ' . $out[2];
                }
                $sql .= $this->where_clause_sql;
                break;

                // mysql
            default:
                $table_src = '';
                if (preg_match('/([a-z_-]+) (?>INNER|LEFT|RIGHT) JOIN/i', $from, $out)) {
                    $table_src = $out[1] . ' ';
                }

                $sql = 'DELETE ' . $table_src . 'FROM ' . trim($from);
                $sql .= $this->where_clause_sql;
                break;
        }

        // Execute the query
        return (bool) $this->execute($sql, $this->where_clause_placeholders, $debug);
    }

    /**
     * Fetches the next row from a result set and returns it according to the $fetch_parameters format
     *
     * @param int $fetch_parameters [OPTIONAL] The PDO fetch style record options
     * @return mixed The next row or false if we reached the end
     */
    public function fetch(int $fetch_parameters = \PDO::FETCH_OBJ): mixed
    {
        if ($this->query instanceof \PDOStatement) {
            return $this->query->fetch($fetch_parameters);
        }
        return false;
    }

    /**
     * Fetches all rows from a result set and return them according to the $fetch_parameters format
     *
     * @param int $fetch_parameters [OPTIONAL] PDO fetch style record options
     * @return mixed The rows according to PDO fetch style or false if no record
     */
    public function fetchAll($fetch_parameters = \PDO::FETCH_OBJ)
    {
        if ($this->query instanceof \PDOStatement) {
            return $this->query->fetchAll($fetch_parameters);
        }
        return false;
    }

    /**
     * Returns the PDO object for external use
     *
     * @return PDO|null The PDO object or null if it's not set
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Get the active PDO driver
     * @return string the driver
     */
    public function getPdoDriver()
    {
        return $this->pdo_driver;
    }

    /**
     * Selects all the tables into the database
     *
     * @param bool $debug [OPTIONAL] Set to true to enable debug mode
     * @return mixed Array with tables if success otherwise false
     */
    public function getTables(bool $debug = false): mixed
    {
        switch ($this->pdo_driver) {
            case 'firebird':
                $qry = 'SELECT TRIM(RDB$RELATION_NAME) FROM RDB$RELATIONS WHERE RDB$VIEW_BLR IS NULL AND (RDB$SYSTEM_FLAG IS NULL OR RDB$SYSTEM_FLAG = 0);';
                break;

            case 'oci':
                $qry = 'SELECT * FROM user_tables ORDER BY table_name';
                break;

            case 'pgsql':
                $qry = 'SELECT table_name FROM information_schema.tables WHERE table_type = \'BASE TABLE\' AND table_schema NOT IN (\'pg_catalog\', \'information_schema\')';
                break;

            // mysql
            default:
                $qry = 'show full tables where Table_Type != \'VIEW\'';
                break;
        }

        $this->query($qry, [], $debug);

        if ($this->row_count < 1) {
            return false;
        }

        return $this->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Get the information about the columns in a given table
     *
     * @param string $table The name of the table
     * @param int $fetch_parameters [OPTIONAL] The PDO fetch style record options
     * @param bool $debug [OPTIONAL] Set to true to enable debug mode
     * @return mixed An associative array that contains the columns data or false if the table doesn't have any column.
     * [
     *     'Field' => string, The name of the column.
     *     'Type' => string, The column data type.
     *     'Null' => string, The column nullability. The value is YES if NULL values can be stored in the column, NO if not.
     *     'Key' => string, The column key if the column is indexed.
     *     'Default' => mixed, The default value for the column.
     *     'Extra' => string, Any additional information. The value is nonempty in these cases:
     *         - auto_increment for columns that have the AUTO_INCREMENT attribute.
     *         - on update CURRENT_TIMESTAMP for TIMESTAMP or DATETIME columns that have the ON UPDATE CURRENT_TIMESTAMP attribute.
     *         - VIRTUAL GENERATED or STORED GENERATED for generated columns.
     *         - DEFAULT_GENERATED for columns that have an expression default value.
     * ]
     */
    public function getColumns(string $table, int $fetch_parameters = \PDO::FETCH_OBJ, bool $debug = false): mixed
    {
        switch ($this->pdo_driver) {
            case 'firebird':
                $qry = 'SELECT
                    TRIM(R.RDB$FIELD_NAME) AS FIELD_NAME,
                    TRIM(R.RDB$DEFAULT_VALUE) AS DEFAULT_VALUE,
                    TRIM(R.RDB$NULL_FLAG) AS NULL_FLAG,
                    TRIM(DECODE(R.RDB$IDENTITY_TYPE, 0, \'ALWAYS\', 1, \'DEFAULT\', \'UNKNOWN\')) AS IDENTITY_TYPE,
                    TRIM(F.RDB$FIELD_LENGTH / RCS.RDB$BYTES_PER_CHARACTER) AS FIELD_LENGTH,
                    TRIM(F.RDB$FIELD_PRECISION) AS FIELD_PRECISION,
                    TRIM(F.RDB$FIELD_SCALE) AS FIELD_SCALE,
                    TRIM(CASE F.RDB$FIELD_TYPE
                        WHEN 7 THEN \'SMALLINT\'
                        WHEN 8 THEN \'INTEGER\'
                        WHEN 10 THEN \'FLOAT\'
                        WHEN 12 THEN \'DATE\'
                        WHEN 13 THEN \'TIME\'
                        WHEN 14 THEN \'CHAR\'
                        WHEN 16 THEN \'BIGINT\'
                        WHEN 27 THEN \'DOUBLE\'
                        WHEN 35 THEN \'TIMESTAMP\'
                        WHEN 37 THEN \'VARCHAR\'
                        WHEN 261 THEN \'BLOB\'
                        ELSE \'UNKNOWN\'
                    END) AS FIELD_TYPE,
                    TRIM(F.RDB$FIELD_SUB_TYPE) AS FIELD_SUB_TYPE
                FROM
                    RDB$FIELDS F
                    LEFT JOIN RDB$RELATION_FIELDS R ON R.RDB$FIELD_SOURCE = F.RDB$FIELD_NAME
                    LEFT JOIN RDB$CHARACTER_SETS RCS ON RCS.RDB$CHARACTER_SET_ID = F.RDB$CHARACTER_SET_ID
                WHERE RDB$RELATION_NAME = ' . $this->safe(trim(\strtoupper($table))) . ' ORDER BY R.RDB$FIELD_POSITION';
                $this->query($qry, [], $debug);
                break;

            case 'oci':
                $qry = 'SELECT *
                    FROM USER_TAB_COLUMNS
                    WHERE TABLE_NAME = :tablename';
                $values = ['tablename' => $table];
                $this->query($qry, $values);
                break;

            case 'pgsql':
                $qry = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = :table_name ORDER BY ordinal_position';
                $values = ['table_name' => $table];
                $this->query($qry, $values);
                break;

            // mysql
            default:
                $qry = 'SHOW COLUMNS FROM ' . trim($table);
                $this->query($qry, [], $debug);
                break;
        }

        if (!empty($this->rowCount())) {
            // Returns the array
            return $this->fetchAll($fetch_parameters);
        }

        return false;
    }

    /**
     * Returns the columns names of the target table in a table
     *
     * @param string $table
     * @param bool   $debug [OPTIONAL] Set to true to enable debug mode
     * @return mixed An array that contains the columns names or false if the table doesn't have any column.
     */
    public function getColumnsNames(string $table, bool $debug = false)
    {
        $columns = $this->getColumns($table, \PDO::FETCH_ASSOC, $debug);

        if (!$columns) {
            return false;
        }

        switch ($this->pdo_driver) {
            case 'firebird':
                $fieldname = 'FIELD_NAME';
                break;

            case 'oci':
                $fieldname = 'COLUMN_NAME';
                break;
            case 'pgsql':
                $fieldname = 'column_name';
                break;

                // mysql
            default:
                $fieldname = 'Field';
                break;
        }

        return $this->convertQueryToSimpleArray($columns, $fieldname);
    }

    /**
     * Checks if inside a transaction.
     *
     * @return bool Returns true if inside a transaction, false otherwise.
     */
    public function inTransaction(): bool
    {
        if ($this->pdo instanceof \PDO) {
            return $this->pdo->inTransaction();
        }

        return false;
    }

    /**
     * Begin transaction processing
     *
     * @return bool Returns true if the transaction begins successfully, false otherwise.
     * @throws \PDOException If there is an error with the database connection.
     * @throws \Exception If there is a general error.
     */
    public function transactionBegin(): bool
    {
        try {
            if (!$this->pdo instanceof \PDO) {
                throw new \PDOException('No database connection');
            }

            if ($this->pdo_driver === 'firebird') {
                $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            }
            // Begin transaction processing
            $success = $this->pdo->beginTransaction();
        } catch (\PDOException $e) { // If there was an error...
            // Return false to show there was an error
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('Database Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        } catch (\Exception $e) { // If there was an error...
            // Return false to show there was an error
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('General Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        }

        return $success;
    }

    /**
     * Commit and end transaction processing.
     *
     * @return bool Returns true if the transaction is committed successfully, false otherwise.
     *
     * @throws \PDOException If there is an error with the database connection.
     * @throws \Exception If there is a general error.
     */
    public function transactionCommit(): bool
    {
        try {
            if (!$this->pdo instanceof \PDO) {
                throw new \PDOException('No database connection');
            }
            // Commit and end transaction processing
            $success = $this->pdo->commit();
            if ($this->pdo_driver === 'firebird') {
                $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            }
        } catch (\PDOException $e) {
            // If there was an error with the database connection
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('Database Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            // If there was a general error
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('General Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        }

        return $success;
    }

    /**
     * Roll back transaction processing.
     *
     * @return bool Returns true if the transaction is rolled back successfully, false otherwise.
     *
     * @throws \PDOException If there is an error with the database connection.
     * @throws \Exception If there is a general error.
     */
    public function transactionRollback(): bool
    {
        try {
            if (!$this->pdo instanceof \PDO) {
                throw new \PDOException('No database connection');
            }
            // Roll back transaction processing
            $success = $this->pdo->rollback();
            if ($this->pdo_driver === 'firebird') {
                $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            }
        } catch (\PDOException $e) {
            // If there was an error with the database connection
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('Database Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            // If there was a general error
            $success = false;

            // Send the error to the error event handler
            $this->errorEvent('General Error (' . __METHOD__ . '): ' . $e->getMessage(), $e->getCode());
        }

        return $success;
    }

    /**
     * Converts a Query() or Select() array of records into a simple array
     * using only one column or an associative array using another column as a key.
     *
     * @param mixed $array The array returned from a PDO query using fetchAll.
     * @param string $value_field The name of the field that holds the value.
     * @param string|null $key_field The name of the field that holds the key, making the return value an associative array.
     * @return array<mixed> Returns an array with only the specified data.
     */
    public function convertQueryToSimpleArray(mixed $array, string $value_field, ?string $key_field = null): array
    {
        // Create an empty array
        $return = [];

        if (is_array($array)) {
            // Loop through the query results
            foreach ($array as $element) {
                // If we have a key
                if (!is_null($key_field)) {
                    // Add this key
                    $return[$element[$key_field]] = $element[$value_field];
                } else { // No key field
                    // Append to the array
                    $return[] = $element[$value_field];
                }
            }
        }

        // Return the new array
        return $return;
    }

    /**
     * This function returns records from a SQL query as an HTML table.
     *
     * @param array<mixed> $records The records set - can be an array or array of objects according to the fetch parameters.
     * @param bool $show_count (Optional) true if you want to show the row count, false if you do not want to show the count.
     * @param string|null $table_attr (Optional) Comma separated attributes for the table. e.g: 'class=my-class, style=color:#222'.
     * @param string|null $th_attr (Optional) Comma separated attributes for the header row. e.g: 'class=my-class, style=font-weight:bold'.
     * @param string|null $td_attr (Optional) Comma separated attributes for the cells. e.g: 'class=my-class, style=font-weight:normal'.
     * @return string HTML containing a table with all records listed.
     */
    public function getHTML(
        array $records,
        bool $show_count = true,
        ?string $table_attr = null,
        ?string $th_attr = null,
        ?string $td_attr = null
    ): string {
        // Set default style information
        if ($table_attr === null) {
            $tb = 'style="border-collapse:collapse;empty-cells:show"';
        } else {
            $tb = $this->getAttributes($table_attr);
        }
        if ($th_attr === null) {
            $th = 'style="border-width:1px;border-style:solid;background-color:navy;color:white"';
        } else {
            $th = $this->getAttributes($th_attr);
        }
        if ($td_attr === null) {
            $td = 'style="border-width:1px;border-style:solid"';
        } else {
            $td = $this->getAttributes($td_attr);
        }

        // If there was no error && records were returned...
        if (is_array($records) && !empty($records)) {
            // Begin the table
            $html = "";
            if ($show_count) {
                $html = "<p>Total Count: " . count($records) . "</p>\n";
            }
            $html .= "<table $tb>\n";

            // Create the header row
            $html .= "\t<tr>\n";
            foreach ($records[0] as $key => $value) {
                $html .= "\t\t<th $th>" . htmlspecialchars($key) . "</th>\n";
            }
            $html .= "\t</tr>\n";

            // Create the rows with data
            foreach ($records as $row) {
                $html .= "\t<tr>\n";
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $value = '';
                    }
                    $html .= "\t\t<td $td>" . htmlspecialchars($value) . "</td>\n";
                }
                $html .= "\t</tr>\n";
            }

            // Close the table
            $html .= "</table>";
        } else { // No records were returned
            $html = "No records were returned.";
        }

        // Return the table HTML code
        return $html;
    }

    /**
     * Converts empty values to NULL
     *
     * @param $value Any value
     * @param bool $include_zero Include 0 as NULL?
     * @param bool $include_false Include false as NULL?
     * @param bool $include_blank_string Include a blank string as NULL?
     * @return mixed The value or NULL if empty
     */
    /**
     * Converts empty values to NULL.
     *
     * @param mixed $value Any value.
     * @param bool $include_zero Include 0 as NULL?
     * @param bool $include_false Include false as NULL?
     * @param bool $include_blank_string Include a blank string as NULL?
     * @return mixed The value or NULL if empty.
     */
    public function emptyToNull(
        mixed $value,
        bool $include_zero = true,
        bool $include_false = true,
        bool $include_blank_string = true
    ): mixed {
        $return = $value;
        if (!$include_false && $value === false) {
            // Skip
        } elseif (is_string($value) && !$include_zero && trim($value) === '0') {
            // Skip
        } elseif (is_int($value) && !$include_zero && $value === 0) {
            // Skip
        } elseif (is_string($value) && !$include_blank_string && trim($value) === '') {
            // Skip
        } elseif (is_string($value)) {
            if (strlen(trim($value)) == 0) {
                $return = null;
            } else {
                $return = trim($value);
            }
        } elseif (empty($value)) {
            $return = null;
        }
        return $return;
    }

    /**
     * Get the error message.
     *
     * @return mixed The error message.
     */
    public function error(): mixed
    {
        return $this->error;
    }

    /**
     * Get the last insert ID.
     *
     * @return mixed The last insert ID.
     */
    public function getLastInsertId(): mixed
    {
        if ($this->driver_supports_last_insert_id) {
            $return = $this->last_insert_id;
        } else {
            try {
                if (!$this->pdo instanceof \PDO) {
                    throw new \PDOException('No database connection');
                }
                $return = $this->pdo->lastInsertId();
            } catch (\PDOException $e) {
                $return = false;
                $err = 'Database Error (' . __METHOD__ . '):<br>'
                    . $e->getMessage() . '<br>Use $db->getMaximumValue($table, $field, $debug = false) instead.';

                // Send the error to the error event handler
                $this->errorEvent($err, $e->getCode());
            }
        }

        return $return;
    }

    /**
     * Determines if a valid connection to the database exists.
     *
     * @return mixed The last SQL query string or null if no query exists.
     */
    public function getLastSql(): mixed
    {
        if (isset($this->query) && $this->query !== null) {
            return $this->query->queryString;
        }
        return null;
    }

    /**
     * Get the last value from a specific table field.
     * @param string $table
     * @param string $field
     * @param bool $debug
     * @return mixed the field value or 1 if no value is found.
     */
    public function getMaximumValue($table, $field, $debug = false)
    {
        $extras = array('order_by' => $field . ' DESC', 'limit' => 1);
        $this->select($table, $field, [], $extras, $debug);
        $row = $this->fetch();

        if ($row) {
            return $row->$field;
        }

        return 1;
    }

    /**
     * Determines if a valid connection to the database exists
     *
     * @return boolean true idf connectect or false if not connected
     */
    public function isConnected()
    {
        return $this->pdo instanceof \PDO;
    }

    /**
     * Gets the content registered by the dumpDebug() function
     *
     * @param string $mode - 'html' or 'json'
     * if 'html' the function will return the HTML output.
     * if 'json' it'll return a JSON output of the HTML content
     * @return string|bool The debug content or false if json_encode fails
     */
    public function getDebugContent($mode = 'html')
    {
        if ($mode === 'json') {
            return json_encode($this->debug_content);
        }

        return $this->debug_content;
    }

    /**
     * Get the number of rows affected by the last database operation.
     *
     * @return mixed The number of rows affected or false on failure.
     */
    public function rowCount(): mixed
    {
        return $this->row_count;
    }

    /**
     * Returns a quoted string that is safe to pass into an SQL statement
     *
     * @param string|\DateTime $value A string value or DateTime object
     * @return string The newly encoded string with quotes
     */
    public function safe($value)
    {
        if (!$this->pdo instanceof \PDO) {
            throw new \PDOException('No database connection');
        }

        // If it's a string...
        if (is_string($value)) {
            // Use PDO to encode it
            return $this->pdo->quote($value);

            // If it's a DateTime object...
        } elseif ($value instanceof \DateTime) {
            // Format the date as a string for MySQL and use PDO to encode it
            return $this->pdo->quote($value->format('Y-m-d H:i:s'));

            // It's something else...
        } else {
            // Return the original value
            return $value;
        }
    }

    /**
     *
     * @param string $debug_mode - 'echo' or 'register'
     * @return void
     */
    public function setDebugMode($debug_mode)
    {
        $this->debug_mode = $debug_mode;
    }

    /**
     * This is an event function that is called every time there is an error.
     * You can add code into this function to do things such as:
     * 1. Log errors into the database
     * 2. Send an email with the error message
     * 3. Save out to some type of log file
     * 4. Make a RESTful API call
     * 5. Run a script or program
     * 6. Set a session or global variable
     * Or anything you might want to do when an error occurs.
     *
     * @param string $error The error description [$exception->getMessage()]
     * @param int $error_code [OPTIONAL] The error number [$exception->getCode()]
     * @return void
     */
    protected function errorEvent(string $error, int $error_code = 0): void
    {
        // Send this error to the PHP error log
        if (function_exists('error_log')) {
            if (empty($error_code)) {
                \error_log($error, 0);
            } else {
                \error_log('DB error ' . $error_code . ': ' . $error, 0);
            }
        }

        // register the error
        $this->error = '<p class="db-error" style="padding: 1rem;"><strong style="color:#70131E; margin-right: 1rem;">Database error ' . $error_code . '</strong>: <em>' . $error . '</em></p>';

        if ($this->show_errors) {
            echo $this->error;
        }
    }

    /**
     * get the limit value(s) and return a cross-driver compatible version.
     * @param int|string $limit int or string with comma-separated values.
     *              e.g: 10 | '10, 20'
    * @return int|string the limit integer or the converted string
     */
    protected function getLimitSqlFromDriver($limit): mixed
    {
        if (is_numeric($limit) || (is_string($limit) && \strpos($limit, ',') === false)) {
            switch ($this->pdo_driver) {
                case 'firebird':
                    $limit_sql = 'FIRST ' . $limit . ' ';
                    break;

                case 'oci':
                    $limit_sql = ' FETCH NEXT ' . $limit . ' ROWS ONLY';
                    break;

                    // mysql, pgsql
                default:
                    $limit_sql = ' LIMIT ' . $limit;
                    break;
            }
        } else {
            $limit = str_replace(' ', '', $limit);
            $limit_values = explode(',', $limit);
            switch ($this->pdo_driver) {
                case 'firebird':
                    $limit_sql = 'FIRST ' . $limit_values[1] . ' SKIP ' . $limit_values[0] . ' ';
                    break;

                case 'oci':
                    $limit_sql = ' OFFSET ' . $limit_values[0] . ' ROWS FETCH NEXT ' . $limit_values[1] . ' ROWS ONLY';
                    break;

                case 'pgsql':
                    $limit_sql = ' LIMIT ' . $limit_values[1] . ' offset ' . $limit_values[0];
                    break;

                    // mysql
                default:
                    $limit_sql = ' LIMIT ' . $limit;
                    break;
            }
        }

        return $limit_sql;
    }

    /** array<string, array<string, bool|DateTime|int|string|null>> but returns array<string, array<string, int<min, -1>|int<1, max>|string>|string>.
     * Builds a SQL WHERE clause from an array
     *
     * @param array<int|string, int<min, -1>|int<1, max>|string>|string $where Array containing the fields and values or a string
     *                    Example:
     *                    $where = [];
     *                    $where['id >'] = 1234;
     *                    $where[] = 'first_name IS NOT NULL';
     *                    $where['some_value <>'] = 'text';
     * @return void
     */
    protected function whereClause($where): void
    {
        // Create an array to hold the place holder values (if any)
        $placeholders = [];

        // Create a variable to hold SQL
        $sql = '';

        // If an array was passed in...
        if (is_array($where) && !empty($where)) {
            // Create an array to hold the WHERE values
            $output = [];

            // loop through the array
            foreach ($where as $key => $value) {
                // If a key is specified for a PDO place holder field...
                if (is_string($key)) {
                    // Extract the key
                    $extracted_key = (string) preg_replace(
                        '/^(\s*)([^\s=<>]*)(.*)/',
                        '${2}',
                        $key
                    );

                    $extracted_key = str_replace('.', '_', $extracted_key);

                    // avoid duplicate keys
                    // and use a prefix to avoid collisions with the $values in select/update queries
                    // use a letter index before the $extracted_key
                    // because Firebird bugs if we use $extracted_key . '_' . $index
                    $index = 0;
                    $alphabet = range('a', 'z');
                    $indexed_key = $alphabet[$index] . '_' . $extracted_key;
                    while (isset($placeholders[$indexed_key])) {
                        $index++;
                        $indexed_key = $alphabet[$index] . '_' . $extracted_key;
                    }
                    $extracted_key = (string) $indexed_key;

                    // If no <> = was specified...
                    if ($alphabet[$index] . '_' . trim(str_replace('.', '_', $key)) == $extracted_key) {
                        // Add the PDO place holder with an =
                        $output[] = trim($key) . ' = :' . $extracted_key;
                    } else { // A comparison exists...
                        // Add the PDO place holder
                        $output[] = trim($key) . ' :' . $extracted_key;
                    }

                    // Add the placeholder replacement values
                    $placeholders[$extracted_key] = $value;
                } else { // No key was specified...
                    $output[] = $value;
                }
            }

            // Concatenate the array values
            $sql = ' WHERE ' . implode(' AND ', $output);
        } elseif (is_string($where) && !empty($where)) {
            $sql = ' WHERE ' . trim($where);
        }

        // Set the sql and placeholders
        $this->where_clause_sql = $sql;
        $this->where_clause_placeholders = $placeholders;
    }

    /**
     * Dump debug information to the screen
     *
     * @param string $source The source to show on the debug output
     * @param string $sql SQL
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] Placeholders array
     * @param \PDOStatement|null $query [OPTIONAL] PDO query object
     * @param bool $records [OPTIONAL] The record count
     * @param float|false $time [OPTIONAL] The number of seconds
     * @param int|bool $count [OPTIONAL] The record count
     * @param string $error [OPTIONAL] Error text
     * @return void
     */
    private function dumpDebug(
        string $source,
        string $sql,
        ?array $placeholders = [],
        ?\PDOStatement $query = null,
        bool $records = false,
        mixed $time = false,
        mixed $count = 0,
        string $error = ''
    ): void {
        $random_colors = array('coral', 'crimson', 'dodgerblue', 'darkcyan', 'darkgoldenrod', 'deeppink', 'forestgreen', 'goldenrod', 'mediumpurple', 'mediumseagreen');
        shuffle($random_colors);

        // Format the source
        $source = '<span style="color:' . $random_colors[0] . '">' . strtoupper($source) . '</span>';

        $output = '<div class="db-dump-debug" style="margin:1rem;padding:1rem;border:1px solid #ccc;">';

        // if INSERT|UPDATE|DELETE
        if (preg_match('`INSERT|UPDATE|DELETE`i', $sql)) {
            $output .= '<p style="color:white;background-color:#C22404;padding:.25rem .5rem;"><strong>DEBUG mode enabled.</strong> The INSERT, UPDATE and DELETE queries are only simulated.</p>';
        }

        // If there was an error specified
        if (!empty($error)) {
            // Show the error information
            $output .= "\n<br>\n--<strong>DEBUG " . $source . " ERROR</strong>--\n
                    <pre><code>";
            $output .= $this->error;
        }

        // If the number of seconds is specified...
        if (is_float($time)) {
            // Show how long it took
            $output .= "</code></pre>\n--<strong>DEBUG " . $source . " TIMER</strong>--\n
                    <pre><code>";
            $output .=  number_format($time, 6) . ' ms';
        }

        // Output the SQL
        $output .= "</code></pre>\n--<strong>DEBUG " . $source . " SQL</strong>--\n
                    <pre><code>";
        $output .= print_r($sql, true);

        // If there were placeholders passed in...
        if ($placeholders) {
            // Show the placeholders
            $output .= "</code></pre>\n--<strong>DEBUG " . $source . " PARAMS</strong>--\n
                    <pre><code>";
            $output .= print_r($placeholders, true);
        }

        // If a query object exists...
        if ($query) {
            // Show the query dump
            ob_start();
            $query->debugDumpParams();
            $qp = ob_get_contents();
            ob_end_clean();
            $output .= "</code></pre>\n--<strong>DEBUG " . $source . " DUMP</strong>--\n
                    <pre><code>";
            $output .= print_r($qp, true);
        }

        // If records were returned...
        if ($count !== false) {
            // Show the count
            $output .= "</code></pre>\n--<strong>DEBUG " . $source . " ROW COUNT</strong>--\n
                    <pre><code>";
            $output .= print_r($count, true);
        }

        // If records were returned...
        if ($records) {
            // Show the rows returned
            $output .= "</code></pre>\n--<strong>DEBUG " . $source . " RETURNED RESULTS</strong>--\n
                    <pre><code>";
            $output .= print_r($records, true);
        }

        // End the debug output
        $output .= "</code></pre>\n--<strong>DEBUG " . $source . " END</strong>--\n
                </div>\n";

        if ($this->debug_mode === 'echo') {
            echo $output;
        } else { // $this->debug_mode = 'register'
            $this->debug_content .= $output;
        }
    }

    /**
     * Returns linearised attributes.
     * @param string $attr The element attributes
     * @return string Linearised attributes
     *                Example: size=30, required=required => size="30" required="required"
     */
    private function getAttributes($attr)
    {
        if (empty($attr)) {
            return '';
        } else {
            $clean_attr = '';

            // replace protected commas with expression
            $attr = str_replace('\\,', '[comma]', $attr);

            // replace protected equals with expression
            $attr = str_replace('\\=', '[equal]', $attr);

            // split with commas
            $attr = preg_split('`,`', $attr);
            if ($attr !== false) {
                foreach ($attr as $a) {
                    // add quotes
                    if (preg_match('`=`', $a)) {
                        $a = preg_replace('`\s*=\s*`', '="', trim($a)) .  '" ';
                    } else {
                        // no quote for single attributes
                        $a = trim($a) . ' ';
                    }
                    $clean_attr .= $a;
                }
            }

            // get back protected commas, equals and trim
            $clean_attr = trim(str_replace(['[comma]', '[equal]'], [',', '='], $clean_attr));

            return $clean_attr;
        }
    }

    /**
     * Interpolates the query by replacing placeholders with their corresponding values.
     *
     * @param string $query The SQL query with placeholders.
     * @param mixed[] $params An array of parameter values to be interpolated into the query.
     * @return string The interpolated query.
     */
    private function interpolateQuery(string $query, array $params): string
    {
        $keys = [];
        $values = [];

        if (!is_array($params)) {
            return $query;
        }

        // Sort $params by key length in descending order.
        uksort($params, function (string $a, string $b): int {
            return strlen($b) - strlen($a);
        });

        // Build a regular expression for each parameter.
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $keys[] = '/:' . $key . '/';
            } else {
                $keys[] = '/[?]/';
            }

            if (is_string($value)) {
                $values[] = "'" . $value . "'";
            } elseif (is_array($value)) {
                $values[] = "'" . implode("','", $value) . "'";
            } elseif (is_null($value)) {
                $values[] = 'NULL';
            } else {
                $values[] = $value;
            }
        }
        $query = preg_replace($keys, $values, $query);

        return (string) $query;
    }

    /**
     * test if the SQL query accepts transaction or if it's an auto-commited query
     * https://dev.mysql.com/doc/refman/5.7/en/implicit-commit.html
     * @param string $sql
     * @return bool
     */
    private function isSqlAutoCommit($sql)
    {
        if (preg_match('/ALTER (DATABASE|EVENT|PROCEDURE|SERVER|TABLE|TABLESPACE|VIEW)/i', $sql) || preg_match('/CREATE (DATABASE|EVENT|INDEX|PROCEDURE|SERVER|TABLE|TABLESPACE|TRIGGER|VIEW)/i', $sql) || preg_match('/(DROP (DATABASE|EVENT|INDEX|PROCEDURE|SERVER|TABLE|TABLESPACE|TRIGGER|VIEW)|INSTALL PLUGIN|LOCK TABLES|RENAME TABLE|TRUNCATE TABLE|UNINSTALL PLUGIN)/i', $sql)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the number of rows in the result set of the current query.
     *
     * @param array<string, string|int|bool|\DateTime|null> $placeholders [OPTIONAL] An array of placeholder values to be bound to the query.
     * @param bool $debug [OPTIONAL] Whether to output debug information.
     * @return int|bool The number of rows, or false on failure.
     */
    private function numRows(array $placeholders = [], bool $debug = false): mixed
    {
        $return = false;
        $time   = false;
        if ($this->query instanceof \PDOStatement) {
            try {
                if (!$this->pdo instanceof \PDO) {
                    throw new \PDOException('No database connection');
                }

                // default: will send the query and fetch all records to count them
                $use_select_count = false;
                $sql = $this->query->queryString;

                // If the query string has no "limit" terms
                // and can be parsed as a SELECT FROM query
                // OFFSET 0 ROWS FETCH NEXT 20 ROWS ONLY
                if (!preg_match('/LIMIT[\s0-9]+|FIRST[\s0-9]+|SKIP[\s0-9]+|OFFSET[\s0-9]+|NEXT[\s0-9]+|HAVING SUM/i', $this->query->queryString) && preg_match('/SELECT (.*) FROM (.*)/i', $this->query->queryString, $out)) {
                    // will send a SELECT COUNT query
                    $use_select_count = true;
                    $sql = 'SELECT COUNT(' . $this->num_rows_query_string . ') AS "row_count" FROM ' . $out[2];

                    // revert the num_rows_query_string to the default
                    $this->num_rows_query_string = "'*'";

                    // Remove the ORDER BY clause
                    if (preg_match('/(.*) ORDER BY (?:.*)$/i', $sql, $out)) {
                        $sql = $out[1];
                    }
                }

                $num_rows_query = $this->pdo->prepare($sql);

                // If there are values in the passed in array
                if (!empty($placeholders) && is_array($placeholders)) {
                    // Loop through the placeholders and values
                    foreach ($placeholders as $field => $value) {
                        // Determine the datatype
                        if (is_int($value)) {
                            $datatype = \PDO::PARAM_INT;
                        } elseif (is_bool($value)) {
                            $datatype = \PDO::PARAM_BOOL;
                        } elseif (is_null($value)) {
                            $datatype = \PDO::PARAM_NULL;
                        } elseif ($value instanceof \DateTime) {
                            $value = $value->format('Y-m-d H:i:s');
                            $placeholders[$field] = $value;
                            $datatype = \PDO::PARAM_STR;
                        } else {
                            $datatype = \PDO::PARAM_STR;
                        }

                        // Bind the placeholder and value to the query
                        $num_rows_query->bindValue($field, $value, $datatype);
                    }
                }

                // Start a timer
                $time_start = microtime(true);

                // Execute the query
                $num_rows_query->execute();

                if ($use_select_count) {
                    $row = $num_rows_query->fetch(\PDO::FETCH_OBJ);

                    // Find out how long the query took
                    $time_end = microtime(true);
                    $time = $time_end - $time_start;

                    // if $row is an object and has the row_count property
                    if (is_object($row) && isset($row->row_count)) {
                        $return = $row->row_count;
                    }
                } else {
                    $rows = $num_rows_query->fetchAll(\PDO::FETCH_OBJ);

                    // Find out how long the query took
                    $time_end = microtime(true);
                    $time = $time_end - $time_start;

                    if ($rows) {
                        $return = count($rows);
                    }
                }

                // Debug only
                if ($debug) {
                    // Output debug information
                    $this->dumpDebug(
                        __FUNCTION__,
                        $sql,
                        $placeholders,
                        $num_rows_query,
                        false,
                        $time,
                        false
                    );
                }
            } catch (\PDOException $e) { // If there was an error...
                if (!isset($sql)) {
                    $sql = '';
                }

                if (!isset($num_rows_query)) {
                    $num_rows_query = false;
                }

                // Get the error
                $err = 'Database Error (' . __METHOD__ . '): '
                    . $e->getMessage() . ' ' . $sql;

                // Send the error to the error event handler
                $this->errorEvent($err, $e->getCode());

                // If we are in debug mode...
                if ($debug) {
                    // Output debug information
                    $this->dumpDebug(
                        __FUNCTION__,
                        $sql,
                        $placeholders,
                        $num_rows_query,
                        $return,
                        $time,
                        false,
                        $err
                    );
                }
            }
        }

        return $return;
    }
}
