<?php
declare(strict_types=1);

namespace Templates\Admin\Lists;

class ResultsListTemplate extends ListTemplate
{

    public function __construct(string $title, $router, int $columnCount, bool $deletesPermitted, array $displayItems, ?array $insertLinkInfo, string $sortColumn, bool $sortByAsc, string $filterFormActionRoute, string $filterOpsList, string $filterValue, string $filterErrorMessage, string $filterFieldName, bool $isFiltered, string $resetFilterRoute, string $csrfNameKey, string $csrfName, string $csrfValueKey, string $csrfValue, bool $updatesPermitted, ?string $updateColumn, ?string $updateRoute, ?string $deleteRoute)
    {
        $headerFields = array_keys($displayItems[0]);
        parent::__construct($title, $router, $columnCount, $deletesPermitted, $displayItems, $insertLinkInfo, $sortColumn, $sortByAsc, $filterFormActionRoute, $filterOpsList, $filterValue, $filterErrorMessage, $filterFieldName, $isFiltered, $resetFilterRoute, $csrfNameKey, $csrfName, $csrfValueKey, $csrfValue, $updatesPermitted, $updateColumn, $updateRoute, $deleteRoute, $headerFields);
    }

    protected function getBodyRow(array $row, int $rowNumber): string {

        $primaryKeyValue = ($this->updateColumn === null) ? null : $row[$this->updateColumn];
        
        return $this->getBodyRowCommon($rowNumber, $row, $this->updatesPermitted, $this->deletesPermitted, $primaryKeyValue);
    }
}
