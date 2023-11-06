<?php

/* database connection

Databases supported by PDO
==========================

Driver          DSN parameter        Supported databases
--------------------------------------------------------
PDO_CUBRID      cubrid               Cubrid
PDO_DBLIB       dblib                FreeTDS / Microsoft SQL Server / Sybase
PDO_FIREBIRD    firebird             Firebird
PDO_IBM         ibm                  IBM DB2
PDO_INFORMIX    informix             IBM Informix Dynamic Server
PDO_MYSQL       mysql                MySQL 3.x/4.x/5.x
PDO_OCI         oci                  Oracle Call Interface
PDO_ODBC        odbc                 ODBC v3 (IBM DB2, unixODBC et win32 ODBC)
PDO_PGSQL       pgsql                PostgreSQL
PDO_SQLITE      sqlite               SQLite 3 et SQLite 2
PDO_SQLSRV      sqlsrv               Microsoft SQL Server / SQL Azure

*/

// DSN parameter of PDO::__construct()
define('PDO_DRIVER', 'mysql');

if (defined('PHPUNIT_TESTSUITE_RUNNIG') || $_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
    // settings for local server (or PHPUNIT testsuite)
    define('DB_HOST', 'localhost');
    define('DB_PORT', ''); // leave empty to use the default port
    define('DB_USER', 'root');
    define('DB_PASS', 'Mysql');
    define('DB_NAME', 'sampledatabase');
} else {
    // settings for remote server
    define('DB_HOST', 'your-db-host.com');
    define('DB_PORT', ''); // leave empty to use the default port
    define('DB_USER', 'username');
    define('DB_PASS', 'password');
    define('DB_NAME', 'sampledatabase');
}
