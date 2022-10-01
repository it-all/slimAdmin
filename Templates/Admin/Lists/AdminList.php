<?php
declare(strict_types=1);

namespace Templates\Admin\Lists;

class AdminList
{
    protected $mainHtml, $headCss, $bodyJs, $listArray, $headers, $footer, $columnCount, $numResults, $hasResults, $title, $numResultsTotal, $showAllLink;

    /**
     *  $numResultsTotal can be used to show that the results set is limited
     *  $numResultsTotal must be greater than or equal to count($listArray)
     */
    public function __construct(string $title, array $listArray, ?array $headers = null, ?array $footers = null, ?string $addBodyJs = null, ?int $numResultsTotal = null, ?string $showAllLink = null)
    {
        $this->title = $title;
        $this->listArray = $listArray;
        $this->headers = $headers;
        $this->footers = $footers;
        $this->headCss = '<link href="'.CSS_DIR_PATH.'/adminFlexListSortable.css" rel="stylesheet" type="text/css">';
        $this->bodyJs = '<script type="text/javascript" src="'.JS_DIR_PATH.'/uiHelper.js"></script><script type="text/javascript" src="'.JS_DIR_PATH.'/sortTable.js"></script>';
        if ($addBodyJs != null) {
            $this->bodyJs .= $addBodyJs;
        }
        $this->numResults = count($this->listArray);
        $this->hasResults = $this->numResults > 0;
        $this->numResultsTotal = $numResultsTotal;
        $this->showAllLink = $showAllLink;
        if ($this->numResultsTotal !== null && $this->numResultsTotal < $this->numResults) {
            throw new \InvalidArgumentException("numResultsTotal ($numResultsTotal) cannot be less than numResults(" . $this->numResults . ")");
        }
        if ($this->numResultsTotal !== null && $this->numResultsTotal > $this->numResults && $this->showAllLink === null) {
            throw new \InvalidArgumentException("show all link must exist when numResultsTotal ($numResultsTotal) gt numResults(" . $this->numResults . ")");
        }
        $this->setColumnCount();
        $this->setMainHtml();
    }

    public function getMainHtml(): string 
    {
        return $this->mainHtml;
    }

    public function getHeadCss(): string 
    {
        return $this->headCss;
    }

    public function getBodyJs(): string 
    {
        return $this->bodyJs;
    }

    protected function setColumnCount() 
    {
        if (is_array($this->headers)) {
            $this->columnCount = count($this->headers);
        } elseif (count($this->listArray) > 0) {
            $this->columnCount = count($this->listArray[0]);
        } else {
            $this->columnCount = 0; // no results
        }
    }

    protected function getTableHeadTitleRow(): string
    {
        $colspan = $this->columnCount ?? 1;
        $numResultsText = $this->numResults;
        if ($this->numResultsTotal !== null && $this->numResultsTotal > $this->numResults) {
            $numResultsText .= ' of <a href="' . $this->showAllLink . '">' . $this->numResultsTotal . '</a>';
        }

        return <<< EOT
        <tr>
            <th colspan="$colspan">
                $this->title ($numResultsText)
            </th>
        </tr>
EOT;
    }

    protected function getTableHead(): string
    {
        $head = '<thead>';
        $head .= $this->getTableHeadTitleRow();
        $head .= '<tr class="sortable-header-row">';
        foreach ($this->headers as $header) {
            $class = mb_strlen($header['class']) === 0 ? '' : ' class="' . $header['class'] . '"';
            $head .= '<th' . $class . ' nowrap="nowrap">'.$header['text'].'</th>';
        }
        $head .= '</tr></thead>';
        return $head;
    }

    protected function getTableBody(): string 
    {
        $body = '<tbody>';
        if (count($this->listArray) === 0) {
            $colspan = count($this->headers) ?? 1;
            $body .= '<tr><td colspan="'.$colspan.'">No results</td></tr>';
        }
        foreach ($this->listArray as $row) {
            $body .= '<tr>';
            foreach ($row as $cellInfo) {
                if (is_array($cellInfo)) {
                    $content = (string) $cellInfo['content'];
                    $classString = ' class="' . implode(" ", $cellInfo['classes']) . '"';
                } else {
                    $content = (string) $cellInfo;
                    $classString = '';
                }

                $body .= '<td' . $classString . ' valign="top">'.$content.'</td>';
            }
            $body .= '</tr>';
        }
        $body .= '</tbody>';
        return $body;
    }

    protected function getTableFoot(): string
    {
        $foot = '<tfoot>';
        $foot .= '<tr>';
        foreach ($this->footers as $footer) {
            $class = mb_strlen($footer['class']) === 0 ? '' : ' class="' . $footer['class'] . '"';
            $foot .= '<td' . $class . '>' . $footer['text'] . '</td>';
        }
        $foot .= '</tr></tfoot>';
        return $foot;
    }

    protected function setTable(): string
    {
        $listTable = '<table class="scrollingTable sortable">';
        if ($this->headers != null) {
            $listTable .= $this->getTableHead();
        }
        $listTable .= $this->getTableBody();
        if ($this->footers != null) {
            $listTable .= $this->getTableFoot();
        }
        $listTable .= '</table>';
        return $listTable;
    }

    protected function setMainHtml()
    {
        $this->mainHtml = '<div id="scrollingTableContainer">';
        $this->mainHtml .= $this->setTable();
        $this->mainHtml .= '</div>';
    }
}
