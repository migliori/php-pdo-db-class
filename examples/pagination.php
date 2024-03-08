<?php

use Migliori\Database\Pagination;
use Migliori\Database\PdoSelectParams;

require_once '../../../autoload.php';

// register the database connection settings
require_once '../connect/db-connect.php';

// create a new PdoSelectParams object with the parameters for the query
$from   = 'customers';
$values = array('id', 'first_name', 'last_name');
$where  = array('country' => 'Indonesia');
$extras = array('order_by' => 'first_name ASC');

$pdo_select_params = new PdoSelectParams($from, $values, $where, $extras);

// set the options for the pagination
$user_options = array(
    'rewrite_links' => false, // disable url rewriting
    'pagination_class' => 'pagination justify-content-center' // set the class for the pagination div
);

$db = new Pagination($pdo_select_params, $user_options);

if (!$db->isConnected()) {
    echo $db->error();
    exit;
}

$url = 'pagination.php';
$pagination_html = $db->pagine($url, 3, 'p');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagination</title>

    <!-- add a Bootstrap theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <!-- add fontawesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
</head>

<body>
    <div class="container">

        <h1 class="text-center py-5">Pagination demo<br><small class=" h6"><a href="https://github.com/migliori/php-pdo-db-class">https://github.com/migliori/php-pdo-db-class</a></small></h1>

        <!-- Navigation -->
        <div class="container text-center mb-2">
            <a href="select.php" class="btn btn-primary m-2"><i class="fas fa-database"></i> Database Select Demo</a>
            <a href="pagination.php" class="btn btn-primary m-2"><i class="fas fa-list"></i> Database Pagination Demo</a>
        </div>

        <div class="d-flex flex-column mx-auto my-5">
            <table class="table mx-auto">
                <thead>
                    <th>First name</th>
                    <th>Last name</th>
                </thead>
                <tbody>
                    <?php
                    // loop through the results
                    while ($row = $db->fetch()) {
                        echo '<tr><td>' . $row->first_name . '</td><td>' . $row->last_name . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="text-center"><?php echo $pagination_html; ?></div>

    </div>
</body>

</html>
