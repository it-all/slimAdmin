<?php
declare(strict_types=1);

require 'bodyRow.php';

$resultsRows = '';
$rowCount = 0;

foreach ($results as $row) {
    $rowCount++;

    // do not allow roles in use to be deleted
    $deletePermitted = in_array($row['id'], $allowDeleteRoles);

    $resultsRows .= bodyRow($row, $rowCount, $updateColumn, $updatePermitted, $updateRoute, $addDeleteColumn, $deletePermitted, $deleteRoute, $router);

}
