<?php
declare(strict_types=1);

namespace Templates\Admin\Lists;

/**
 * generates a sortable html table from the received listArray and optional headers with a title row that has an optional link and filter form
 * allows hidden rows 
 */
class AdminFilterableListContent extends AdminList
{
    private $captionLinkInfo, $filterForm, $errorMessage, $listTitle;


    /**
     * input listTitle if want to use something other than title
     *  $numResultsTotal can be used to show that the results set is limited
     *  $numResultsTotal must be greater than or equal to count($listArray)
     */
    public function __construct(array $listArray, ?array $headers, string $title, ?array $captionLinkInfo = null, ?string $filterForm = null, string $errorMessage = null, ?string $addBodyJs = null, ?array $footers = null, ?string $listTitle = null, ?int $numResultsTotal = null, ?string $showAllLink = null)
    {
        $this->captionLinkInfo = $captionLinkInfo;
        $this->filterForm = $filterForm;
        $this->errorMessage = $errorMessage;
        $this->listTitle = $listTitle;
        parent::__construct($title, $listArray, $headers, $footers, $addBodyJs, $numResultsTotal, $showAllLink);
    }

    protected function getTableHeadTitleRow(): string
    {
        $resultsCount = count($this->listArray);
        $captionLinkHtml = ($this->captionLinkInfo != null) ? '<a class="tableCaptionAction" href="'.$this->captionLinkInfo['href'].'">'.$this->captionLinkInfo['text'].'</a>' : '';
        $filterForm = $this->filterForm ?? '';
        $colspan = $this->columnCount ?? 1;
        $tableHeadTitle = $this->listTitle ?? $this->title;

        $numResultsText = $this->numResults;
        if ($this->numResultsTotal !== null && $this->numResultsTotal > $this->numResults) {
            $numResultsText .= ' of <a href="' . $this->showAllLink . '">' . $this->numResultsTotal . '</a>';
        }

        return <<< EOT
        <tr>
            <th colspan="$colspan">
                $tableHeadTitle ($numResultsText)
                $captionLinkHtml
                $filterForm
            </th>
        </tr>
EOT;
    }

    protected function getTableBody(): string 
    {
        $body = '<tbody id="tbody">';
        $rowNumber = 0;
        $colspan = $this->columnCount ?? 1;
        if ($this->errorMessage !== null) {
            $body .= '<tr><td colspan="'.$colspan.'" class="adminNoticeFailure">'.$this->errorMessage.'</td></tr>';
        }
        if (!$this->hasResults) {
            $body .= '<tr><td colspan="'.$colspan.'">No results</td></tr>';
        }
        foreach ($this->listArray as $row) {
            $rowNumber++;
            $hiddenRowsAfter = [];
            $body .= '<tr id="row'.$rowNumber.'">';
            foreach ($row as $cellName => $cellInfo) {
                if ($cellName === 'hiddenRows') {
                    $hiddenRowsAfter = $cellInfo; // $cellContent is array of the hidden rows
                } else {
                    if (is_array($cellInfo)) {
                        $content = (string) $cellInfo['content'];
                        $classString = ' class="' . implode(" ", $cellInfo['classes']) . '"';
                    } else {
                        $content = (string) $cellInfo;
                        $classString = '';
                    }
                    $body .= '<td' . $classString . ' valign="top">'.$content.'</td>';
                }
            }
            $body .= '</tr>';
            if (count($hiddenRowsAfter) > 0) {
                foreach($hiddenRowsAfter as $hiddenRowDivId => $hiddenRowContent) {
                    $body .= '<tr id="'.$hiddenRowDivId.'" style="display: none;"><td colspan="'.$colspan.'" align="center" width="100%">'.$hiddenRowContent.'</td></tr>';
                }
            }
        }
        $body .= '</tbody>';
        return $body;
    }
}
