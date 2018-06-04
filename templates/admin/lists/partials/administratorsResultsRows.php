<?php
declare(strict_types=1);

require 'bodyRow.php';

$resultsRows = '';
$rowCount = 0;

foreach ($results as $row) {
    $rowCount++;

    // do not allow non-owners to edit owners
    $rowUpdatePermitted = ($authentication->getUserRole() != 'owner' && $row['role'] == 'owner') ? false : $updatePermitted;

    // do not allow admin to delete themself or non-owners to delete owners
    $rowDeletePermitted = $row['username'] != $authentication->getUserUsername() && ($authentication->getUserRole() == 'owner' || $row['role'] != 'owner');

    $resultsRows .= bodyRow($row, $rowCount, $updateColumn, $rowUpdatePermitted, $updateRoute, $addDeleteColumn, $rowDeletePermitted, $deleteRoute, $router);

}
