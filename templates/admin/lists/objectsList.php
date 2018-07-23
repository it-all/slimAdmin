<?php
declare(strict_types=1);

use Templates\Admin\Lists\ObjectsListTemplate;

$listTemplate = new ObjectsListTemplate($title, $router, $columnCount, $deletesPermitted, $displayItems, $insertLinkInfo, $sortColumn, $sortByAsc, $filterFormActionRoute, $filterOpsList, $filterValue, $filterErrorMessage, $filterFieldName, $isFiltered, $resetFilterRoute, $csrfNameKey, $csrfName, $csrfValueKey, $csrfValue, $updatesPermitted, $updateColumn, $updateRoute, $deleteRoute);

require 'list.php';
