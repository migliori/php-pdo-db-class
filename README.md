
# PHP PDO Database class

![Static Badge](https://img.shields.io/badge/php%207.3+-fafafa?logo=php) [![GPLv3 License](https://img.shields.io/badge/License-GPL%20v3-yellow.svg)](https://opensource.org/licenses/)

This DB class provides a set of simple, intuitive methods for executing queries and retrieving data. It handles pagination, error handling and debugging.

The code is dulley documented with PHPDOC. It provides types & type hinting and follows the highest coding standards (PHPSTAN Level 9).

## Requirements

PHP ^7.4, PHP 8.x

## Documentation

[PHP PDO Database class - Detailed documentation, functions reference & code samples](https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php)

## Installation

Clone / download or install with Composer

```bash
  composer require vendor/migliori/php-pdo-database-class
```

## Usage/Examples

1. Open `database/db-connect.php` in your code editor and set the followings constants to connect to your database:

    ```php
    PDO_DRIVER // 'mysql', 'firebird', 'oci' or 'pgsql'
    DB_HOST    // For instance 'localhost'
    DB_NAME    // Your database name
    DB_USER    // Your database username
    DB_PASS    // Your database password
    DB_PORT[OPTIONAL]    // The default port is 3306
    ```

2. Require `database/db-connect.php` and you can connect to both your localhost and production server using `$db = new DB();` without any argument.

    ```php
    use database\DB;

    // register the database connection settings
    require_once 'database/db-connect.php';

    // Include the database class
    require_once 'database/DB.php';

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

    foreach($rows as $row) {
        echo $row->first_name . ' ' . $row->last_name . '<br>';
    }
    ```

To see all the public methods and more examples of use of use visit [https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php](https://www.phpformbuilder.pro/documentation/php-pdo-database-class.php)

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
