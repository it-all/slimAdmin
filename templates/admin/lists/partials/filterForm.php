<?php
declare(strict_types=1);

$ffAction = $router->pathFor($filterFormActionRoute);
$ffError = ($filterErrorMessage) ? '<span class="ffErrorMsg">'.$filterErrorMessage.'</span>' : '';
$ffReset = ($isFiltered) ? '<a href="'.$router->pathFor($resetFilterRoute).'">reset</a>' : '';

$filterForm = <<< EOT
<form name="filter" method="post" style="display: inline" action="$ffAction">
    SELECT WHERE
    $ffError
    <input type="text" name="$filterFieldName" value="$filterValue" size="55" maxlength="500" placeholder="field1:{op}:value 1[,field2...] op in [$filterOpsList ]" required>
    <input type="submit" value="Filter">
    $ffReset
    <input type="hidden" name="$csrfNameKey" value="$csrfName">
    <input type="hidden" name="$csrfValueKey" value="$csrfValue">
</form>
EOT;
