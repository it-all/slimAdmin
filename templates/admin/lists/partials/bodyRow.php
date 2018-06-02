<?php
declare(strict_types=1);

function bodyRow(array $row, int $rowNumber, ?string $updateColumn, bool $updatePermitted, ?string $updateRoute, bool $addDeleteColumn, bool $deletePermitted, ?string $deleteRoute, $router): string {
    $bodyRow = '<tr id="row'.$rowNumber.'">';
    foreach ($row as $key => $value) {
        if ($key != 'metaDisableDelete') {
            if ($key == $updateColumn && $updatePermitted && $updateRoute !== null) {
                $bodyRow .= '<td><a href="'.$router->pathFor($updateRoute, ["primaryKey" => $row[$updateColumn]]).'" title="update">'.$value.'</a></td>';
            } else {
                $bodyRow .= '<td>'.$value.'</td>';
            }
        }
    }
    if ($addDeleteColumn) {
        $bodyRow .= ($deletePermitted && $deleteRoute !== null) ? '<td><a href="'.$router->pathFor($deleteRoute, ["primaryKey" => $row[$updateColumn]]).'" title="delete" onclick="return confirm(\'Are you sure you want to delete record '.$row[$updateColumn].'?\');">X</a></td>' : '<td>&nbsp;</td>';
    }
    $bodyRow .= '</tr>';

    return $bodyRow;
}
