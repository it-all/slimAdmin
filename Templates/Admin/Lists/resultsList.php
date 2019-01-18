<?php
declare(strict_types=1);

use Templates\Admin\Lists\ResultsListTemplate;

$listTemplate = new ResultsListTemplate($title, $router, $columnCount, $deletesPermitted, $displayItems, $insertLinkInfo, $sortColumn, $sortAscending, $filterFormActionRoute, $filterOpsList, $filterValue, $filterErrorMessage, $filterFieldName, $isFiltered, $resetFilterRoute, $csrfNameKey, $csrfName, $csrfValueKey, $csrfValue, $updatesPermitted, $updateColumn, $updateRoute, $deleteRoute);

require 'list.php';
