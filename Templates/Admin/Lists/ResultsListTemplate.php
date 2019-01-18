<?php
declare(strict_types=1);

namespace Templates\Admin\Lists;
use Slim\Router;

class ResultsListTemplate extends ListTemplate
{

    public function __construct(string $title, Router $router, int $columnCount, bool $deletesPermitted, ?array $displayItems, ?array $insertLinkInfo, string $sortColumn, bool $sortAscending, string $filterFormActionRoute, string $filterOpsList, string $filterValue, string $filterErrorMessage, string $filterFieldName, bool $isFiltered, string $resetFilterRoute, string $csrfNameKey, string $csrfName, string $csrfValueKey, string $csrfValue, bool $updatesPermitted, ?string $updateColumn, ?string $updateRoute, ?string $deleteRoute)
    {
        $headerFields = ($displayItems !== null && count($displayItems) > 0) ? array_keys($displayItems[0]) : [];
        parent::__construct($title, $router, $columnCount, $deletesPermitted, $displayItems, $insertLinkInfo, $sortColumn, $sortAscending, $filterFormActionRoute, $filterOpsList, $filterValue, $filterErrorMessage, $filterFieldName, $isFiltered, $resetFilterRoute, $csrfNameKey, $csrfName, $csrfValueKey, $csrfValue, $updatesPermitted, $updateColumn, $updateRoute, $deleteRoute, $headerFields);
    }

    protected function getBodyRow(array $row, int $rowNumber): string {

        $primaryKeyValue = ($this->updateColumn === null) ? null : $row[$this->updateColumn];
        
        return $this->getBodyRowCommon($rowNumber, $row, $this->updatesPermitted, $this->deletesPermitted, $primaryKeyValue);
    }
}
