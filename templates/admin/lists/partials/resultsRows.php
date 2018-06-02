<?php
declare(strict_types=1);

require 'bodyRow.php';

$resultsRows = '';
$rowCount = 0;

foreach ($results as $row) {
    $rowCount++;
    $deletePermitted = !isset($row['metaDisableDelete']) || !$row['metaDisableDelete'];
    $resultsRows .= bodyRow($row, $rowCount, $updateColumn, $updatePermitted, $updateRoute, $addDeleteColumn, $deletePermitted, $deleteRoute, $router);
}
