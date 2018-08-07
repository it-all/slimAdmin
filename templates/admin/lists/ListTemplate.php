<?php
declare(strict_types=1);

namespace Templates\Admin\Lists;

use Slim\Router;

abstract class ListTemplate
{
    protected $title;
    protected $router;
    protected $columnCount;
    protected $deletesPermitted;
    protected $displayItems;
    protected $insertLink;
    protected $sortColumn;
    protected $sortByAsc;
    protected $filterFormActionRoute;
    protected $filterOpsList;
    protected $filterValue;
    protected $filterErrorMessage;
    protected $filterFieldName;
    protected $isFiltered;
    protected $resetFilterRoute;
    protected $csrfNameKey;
    protected $csrfName;
    protected $csrfValueKey;
    protected $csrfValue;
    protected $updatesPermitted;
    protected $updateColumn;
    protected $updateRoute;
    protected $deleteRoute;


    protected $headerFields;

    protected $numResults;

    public function __construct(string $title, Router $router, int $columnCount, bool $deletesPermitted, array $displayItems, ?array $insertLinkInfo, string $sortColumn, bool $sortByAsc, string $filterFormActionRoute, string $filterOpsList, string $filterValue, string $filterErrorMessage, string $filterFieldName, bool $isFiltered, string $resetFilterRoute, string $csrfNameKey, string $csrfName, string $csrfValueKey, string $csrfValue, bool $updatesPermitted, ?string $updateColumn, ?string $updateRoute, ?string $deleteRoute, array $headerFields)
    {
        $this->title = $title;
        $this->router = $router;
        $this->columnCount = $columnCount;
        $this->deletesPermitted = $deletesPermitted;
        $this->displayItems = $displayItems;
        $this->insertLinkInfo = $insertLinkInfo;
        $this->sortColumn = $sortColumn;
        $this->sortByAsc = $sortByAsc;
        $this->filterFormActionRoute = $filterFormActionRoute;
        $this->filterOpsList = $filterOpsList;
        $this->filterValue = $filterValue;
        $this->filterErrorMessage = $filterErrorMessage;
        $this->filterFieldName = $filterFieldName;
        $this->isFiltered = $isFiltered;
        $this->resetFilterRoute = $resetFilterRoute;
        $this->csrfNameKey = $csrfNameKey;
        $this->csrfName = $csrfName;
        $this->csrfValueKey = $csrfValueKey;
        $this->csrfValue = $csrfValue;
        $this->updatesPermitted = $updatesPermitted;
        $this->updateColumn = $updateColumn;
        $this->updateRoute = $updateRoute;
        $this->deleteRoute = $deleteRoute;
        
        $this->headerFields = $headerFields;
        
        $this->numResults = count($this->displayItems);
    }

    public function getStartMain(): string 
    {
        $startMain = <<< EOT
<main>
    <div id="scrollingTableContainer">
        <table class="scrollingTable sortable">
            <thead>
EOT;

$colspan = $this->columnCount;
if ($this->deletesPermitted) {
    $colspan++;
}

$insertLinkHtml = ($this->insertLinkInfo != null) ? '<a class="tableCaptionAction" href="'.$this->router->pathFor($this->insertLinkInfo['route']).'">'.$this->insertLinkInfo['text'].'</a>' : '';
$filterForm = $this->getFilterForm();

$startMain .= <<< EOT
                <tr>
                    <th colspan="$colspan">
                        $this->title ($this->numResults)
                        $insertLinkHtml
                        $filterForm
                    </th>
                </tr>
EOT;

if ($this->numResults > 0) {
    $startMain .= '<tr class="sortable-header-row">';
    foreach ($this->headerFields as $headerKey) {
        $sortClass = ($this->sortByAsc) ? 'sorttable_sorted' : 'sorttable_sorted_reverse';
        $thClass = ($headerKey == $this->sortColumn) ? $sortClass : '';
        $startMain .= '<th class="'.$thClass.'">'.$headerKey.'</th>';
    }
    
    if ($this->deletesPermitted) {
        $startMain .= '<th class="sorttable_nosort">X</th>';
    }
    $startMain .= '</tr>';
}

$startMain .= <<< EOT
            </thead>
            <tbody id="tbody">
EOT;
        return $startMain;
    }

    private function getFilterForm(): string 
    {
        $ffAction = $this->router->pathFor($this->filterFormActionRoute);
        $ffError = (mb_strlen($this->filterErrorMessage) > 0) ? '<span class="ffErrorMsg">'.$this->filterErrorMessage.'</span>' : '';
        $ffReset = ($this->isFiltered) ? '<a href="'.$this->router->pathFor($this->resetFilterRoute).'">reset</a>' : '';

        $filterValue = htmlspecialchars($this->filterValue, ENT_QUOTES|ENT_HTML5);
        $filterForm = <<< EOT
<form name="filter" method="post" style="display: inline" action="$ffAction">
    SELECT WHERE
    <input type="text" name="$this->filterFieldName" value="$filterValue" size="58" maxlength="500" placeholder="field1:op:val1[,field2...] op in [$this->filterOpsList]" required>
    <input type="submit" value="Filter">
    $ffError
    $ffReset
    <input type="hidden" name="$this->csrfNameKey" value="$this->csrfName">
    <input type="hidden" name="$this->csrfValueKey" value="$this->csrfValue">
</form>
EOT;
        return $filterForm;
    }

    public function getResultsRows(): string 
    {
        $resultsRows = '';
        $rowCount = 0;

        if ($this->numResults > 0) {
            foreach ($this->displayItems as $row) {
                // row is either results array or object
                $rowCount++;
                $resultsRows .= $this->getBodyRow($row, $rowCount);
            }
        } else {
            $resultsRows .= '<tr><td>No results</td></tr>';
        }

        return $resultsRows;
    }

    protected function getCell(string $fieldName, $fieldValue, bool $showUpdateLink, ?string $primaryKeyValue): string 
    {
        if ($showUpdateLink && $this->updateRoute == null) {
            throw new \Exception("Must have updateRoute");
        }

        if ($showUpdateLink && $primaryKeyValue === null) {
            throw new \Exception("Must have primaryKeyValue");
        }

        // either the update link or just the value
        $cellValue = ($fieldName == $this->updateColumn && $showUpdateLink) ? '<a href="'.$this->router->pathFor($this->updateRoute, ["primaryKey" => $primaryKeyValue]).'" title="update">'.$fieldValue.'</a>' : $fieldValue;
    
        return '<td>'.$cellValue.'</td>';
    }

    /** the delete column - either delete link or blank space */
    protected function getDeleteCell(bool $showDeleteLink, ?string $primaryKeyValue): string 
    {
        if ($showDeleteLink && $this->deleteRoute == null) {
            throw new \Exception("Must have deleteRoute");
        }
        if ($showDeleteLink && $primaryKeyValue === null) {
            throw new \Exception("Must have primaryKeyValue to delete");
        }
    
        $cellValue = ($showDeleteLink) ? '<a href="'.$this->router->pathFor($this->deleteRoute, ["primaryKey" => $primaryKeyValue]).'" title="delete" onclick="return confirm(\'Are you sure you want to delete '.$primaryKeyValue.'?\');">X</a>' : '&nbsp;';

        return '<td>'.$cellValue.'</td>';
    }

    protected function getBodyRowCommon(int $rowNumber, array $fields, bool $showUpdateLink, bool $showDeleteLink, ?string $primaryKeyValue): string 
    {
        
        $bodyRow = '<tr id="row'.$rowNumber.'">';
        foreach ($fields as $fieldName => $fieldValue) {
            $bodyRow .= $this->getCell($fieldName, $fieldValue, $showUpdateLink, $primaryKeyValue);
        }
        if ($this->deletesPermitted) {
            $bodyRow .= $this->getDeleteCell($showDeleteLink, $primaryKeyValue);
        }
        $bodyRow .= '</tr>';
    
        return $bodyRow;
    }
    
}
