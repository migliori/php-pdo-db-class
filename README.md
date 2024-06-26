
# [DEPRECATED] PHP PDO Database class

![Static Badge](https://img.shields.io/badge/php%207.4+-fafafa?logo=php) [![GPLv3 License](https://img.shields.io/badge/License-GPL%20v3-yellow.svg)](https://opensource.org/licenses/)

> :warning: **DEPRECATION NOTICE**: This package is no longer maintained and has been deprecated. It's been replaced by [PowerLite PDO](https://www.powerlitepdo.com). Please visit the [PowerLite PDO GitHub repository](https://github.com/migliori/power-lite-pdo) for the latest updates and support.

This DB class provides a set of simple, intuitive methods for executing queries and retrieving data. It handles pagination, error handling and debugging.

The code is fully documented with PHPDOC. It provides types & type hinting and follows the highest coding standards (PHPSTAN Level 9).

## Demo

[PDO Database class - queries and pagination demos](https://www.phpformbuilder.pro/phpformbuilder/vendor/migliori/php-pdo-database-class/examples/select.php)

## Features

- Connection to any MySQL, Firebird, OCI (Oracle) or Pgsql (PostgreSQL) database
- SQL queries Sending
- Generation and sending of prepared PDO queries
- Functions available for all types of queries:
  - Select
  - SelectCount
  - SelectRow
  - SelectValue
  - Query
  - QueryRow
  - QueryValue
  - Execute
  - Insert
  - Update
  - Delete
  - GetColums
  - GetColumnsNames
  - GetTables
  - TransactionBegin
  - TransactionCommit
  - TransactionRollback
- Pagination with configuration and options
- Register your connection settings in a single safe place
- DEBUG mode - display of SQL queries sent to the server and detailed information
- Error event handling and PHP error logging with try/catch

## Requirements

PHP ^7.4, PHP 8.x

## Documentation

[PHP PDO Database class - Full detailed documentation, functions reference & code samples](https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php)

## Installation

Clone / download or install with Composer

```bash
  composer require migliori/php-pdo-database-class
```

## Usage/Examples

1. Open `src/connect/db-connect.php` in your code editor and set the followings constants to connect to your database:

    ```php
    PDO_DRIVER // 'mysql', 'firebird', 'oci' or 'pgsql'
    DB_HOST    // For instance 'localhost'
    DB_NAME    // Your database name
    DB_USER    // Your database username
    DB_PASS    // Your database password
    DB_PORT[OPTIONAL]    // The default port is 3306
    ```

2. Require `src/connect/db-connect.php` and you can connect to both your localhost and production server using `$db = new DB();` without any argument.

    ```php
    use Migliori\Database\Db;

    // register the database connection settings
    require_once 'src/connect/db-connect.php';

    // Then connect to the database
    $db = new DB();

    // or connect and show all the encountered errors automatically
    $db = new DB(true);

    // or connect, then test the connection and retrieve the error if the database is not connected
    $db = new DB();
    if (!$db->isConnected()) {
        $error_message = $db->error();
    }
    ```

3. Call the methods to send your queries and retrieve the results.

    ```php
    // Select rows without using SQL
    $values = array('id', 'first_name', 'last_name');
    $where = array('country' => 'Indonesia');
    $db->select('customers', $values, $where);

    // We can make more complex where clauses in the Select, Update, and Delete methods
    $values = array('id', 'first_name', 'last_name');
    $where = array(
        'zip_code IS NOT NULL',
        'id >' => 10,
        'last_name LIKE' => '%Ge%'
    );
    $db->select('customers', $values, $where);

    // Let's sort by descending ID and run it in debug mode
    $extras = array('order_by' => 'id DESC');
    $db->select('customers', $values, $where, $extras, true);


    // loop through the results
    while ($row = $db->fetch()) {
        echo $row->first_name . ' ' . $row->last_name . '<br>';
    }

    // or fetch all the records then loop
    // (this function should not be used if a huge number of rows have been selected, otherwise it will consume a lot of memory)
    $rows = $db->fetchAll();

    foreach ($rows as $row) {
        echo $row->first_name . ' ' . $row->last_name . '<br>';
    }
    ```

To see all the public methods and more examples of use of use visit [https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php](https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php)

## Example with Pagination

1. Open `database/db-connect.php` in your code editor and set the followings constants to connect to your database:

    ```php
    PDO_DRIVER // 'mysql', 'firebird', 'oci' or 'pgsql'
    DB_HOST    // For instance 'localhost'
    DB_NAME    // Your database name
    DB_USER    // Your database username
    DB_PASS    // Your database password
    DB_PORT[OPTIONAL]    // The default port is 3306
    ```

2. Get your records and the pagination HTML code

    ```php
    use Migliori\Database\Pagination;
    use Migliori\Database\PdoSelectParams;

    // register the database connection settings
    require_once 'src/connect/db-connect.php';

    // register the PDO parameters for the query in a PdoSelectParams() object
    $values = array('id', 'first_name', 'last_name');
    $where = array('country' => 'Indonesia');

    $pdo_select_params = new PdoSelectParams('customers', $values, $where);

    // create the Pagination object
    $db = new Pagination($pdo_select_params);

    // get the records and the pagination HTML code
    $pagination_html = $db->pagine();

    // count the records and display them
    $records_count = $db->rowCount();

    if (!empty($records_count)) {
        while ($row = $db->fetch()) {
            echo $row->first_name . ' ' . $row->last_name . ' : ' . $row->country . '<br>';
        }
    }

    echo $pagination_html;

    ```

## Running Tests

To run tests, run the following command

```bash
php ./vendor/bin/phpunit test
```

## Contributing

Contributions are always welcome!

Please contact us for any improvement suggestions or send your pull requests

## License

[GNU General Public License v3.0](https://choosealicense.com/licenses/gpl-3.0/)
