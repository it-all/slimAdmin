<?php
declare(strict_types=1);

require 'bodyRow.php';

$resultsRows = '';
$rowCount = 0;

foreach ($results as $row) {
    $rowCount++;

    // do not allow non-owners to edit owners
//    $rowUpdatePermitted = ($authentication->getUserRole() != 'owner' && $row['role'] == 'owner') ? false : $updatePermitted;

    // todo fix
    $rowUpdatePermitted = true;

    // do not allow admin to delete themself or non-owners to delete owners
//    $rowDeletePermitted = $row['username'] != $authentication->getAdministratorUsername() && ($authentication->getUserRole() == 'owner' || $row['role'] != 'owner');
//
    // todo fix
    $rowDeletePermitted = true;

    $resultsRows .= bodyRow($row, $rowCount, $updateColumn, $rowUpdatePermitted, $updateRoute, $addDeleteColumn, $rowDeletePermitted, $deleteRoute, $router);

}
