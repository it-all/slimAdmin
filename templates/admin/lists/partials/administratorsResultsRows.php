<?php
declare(strict_types=1);

require 'bodyRow.php';

$resultsRows = '';
$rowCount = 0;

foreach ($results as $row) {
    $rowCount++;

    // do not allow non-owners to edit owners
    $rowUpdatePermitted = (!$authorization->hasRole('owner') && in_array('owner', $row['roles'])) ? false : true;

    // do not allow admin to delete themself or non-owners to delete owners
    $rowDeletePermitted = $row['username'] != $authentication->getAdministratorUsername() && ($authorization->hasRole('owner') || !in_array('owner', $row['roles']));

    // change roles field from array to string
    $row['roles'] = implode(", ", $row['roles']);

    $resultsRows .= bodyRow($row, $rowCount, $updateColumn, $rowUpdatePermitted, $updateRoute, $addDeleteColumn, $rowDeletePermitted, $deleteRoute, $router);

}
