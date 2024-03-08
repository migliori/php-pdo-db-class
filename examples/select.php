<?php

use Migliori\Database\Db;

require_once '../../../autoload.php';

// register the database connection settings
require_once '../src/connect/db-connect.php';

// connect, then test the connection and retrieve the error if the database is not connected
$db = new Db();

if (!$db->isConnected()) {
    $error_message = $db->error();
}

// select rows without using SQL
$values = array('id', 'first_name', 'last_name');
$where = array('country' => 'Indonesia');
$db->select('customers', $values, $where);

$demo_code_title = [
    0 => '<h2 class="h4">Example 1: select all the customers from Indonesia</h2>',
    1 => '<h2 class="h4">Example 2: select customers with ZIP code, ID > 10, and last name containing "Ma"</h2>',
    2 => '<h2 class="h4">Example 3: same request as example 2 but ordered by id DESC with debug mode enabled</h2>',
    3 => '<h2 class="h4">Example 4: select customers from San Francisco, California, fetch all the records then loop</h2>'
];

$demo_code = [
    0 => '',
    1 => '',
    2 => '',
    3 => ''
];

// loop through the results and populate the demo_code array for Example 1
while ($row = $db->fetch()) {
    $demo_code[0] .= '<tr><td>' . $row->first_name . '</td><td>' . $row->last_name . '</td></tr>';
}

// We can make more complex where clauses in the Select, Update, and Delete methods
$values = array('id', 'first_name', 'last_name');
$where = array(
    'zip_code IS NOT NULL',
    'id >' => 10,
    'last_name LIKE' => '%Ma%'
);
$db->select('customers', $values, $where);

// loop through the results and populate the demo_code array for Example 2
while ($row = $db->fetch()) {
    $demo_code[1] .= '<tr><td>' . $row->first_name . '</td><td>' . $row->last_name . '</td></tr>';
}

// Let's sort by descending ID and run it in debug mode
$extras = array('order_by' => 'id DESC');
$db->setDebugMode('register');
$db->select('customers', $values, $where, $extras, true);

// loop through the results and populate the demo_code array for Example 3
while ($row = $db->fetch()) {
    $demo_code[2] .= '<tr><td>' . $row->first_name . '</td><td>' . $row->last_name . '</td></tr>';
}
$demo_code[2] .= '<tr><td colspan="2" class="small">' . $db->getDebugContent() . '</td></tr>';

$values = array('id', 'first_name', 'last_name');
$where = array('city' => 'San Francisco');
$extras = array('order_by' => 'first_name ASC');
$db->select('customers', $values, $where);

// fetch all the records and populate the demo_code array for Example 4
$rows = $db->fetchAll();

foreach ($rows as $row) {
    $demo_code[3] .= '<tr><td>' . $row->first_name . '</td><td>' . $row->last_name . '</td></tr>';
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP PDO Database demo</title>

    <!-- add a Bootstrap theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- add fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
</head>

<body>

    <h1 class="text-center py-5">PHP PDO Database demo<br><small class=" h6"><a href="https://github.com/migliori/php-pdo-db-class">https://github.com/migliori/php-pdo-db-class</a></small></h1>

    <!-- Navigation -->
    <div class="container text-center mb-2">
        <a href="select.php" class="btn btn-primary m-2"><i class="fas fa-database"></i> Database Select Demo</a>
        <a href="pagination.php" class="btn btn-primary m-2"><i class="fas fa-list"></i> Database Pagination Demo</a>
    </div>

    <?php for ($i = 0; $i < 4; $i++) { ?>
        <div class="container">
            <?php echo $demo_code_title[$i] ?>
            <div class="card mb-5">
                <div class="card-body overflow-auto">
                    <table class="table table-striped table-sm mx-auto">
                        <thead>
                            <th>First name</th>
                            <th>Last name</th>
                        </thead>
                        <tbody>
                            <?php echo $demo_code[$i] ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

</body>

</html>
