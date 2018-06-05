<?php
declare(strict_types=1);

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new \SlimPostgres\App();

$q = new \SlimPostgres\Database\Queries\QueryBuilder("SELECT * FROM roles");
$res = $q->execute();

while ($row = pg_fetch_assoc($res)) {
    echo $row['role'] . "\n";
}
