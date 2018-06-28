<?php
declare(strict_types=1);

$startMain = <<< EOT
<main>
    <div id="scrollingTableContainer">
        <table class="scrollingTable sortable">
            <thead>
EOT;

$colspan = $numColumns;
if ($addDeleteColumn) {
    $colspan++;
}

$insertLinkHtml = (isset($insertLink)) ? '<a class="tableCaptionAction" href="'.$router->pathFor($insertLink['route']).'">'.$insertLink['text'].'</a>' : '';

require 'filterForm.php';

$startMain .= <<< EOT
                <tr>
                    <th colspan="$colspan">
                        $title ($numResults)
                        $insertLinkHtml
                        $filterForm
                    </th>
                </tr>
EOT;

if ($numResults > 0) {
    $startMain .= '<tr class="sortable-header-row">';
    foreach ($results[0] as $headerKey => $value) {
        if ($headerKey != 'metaDisableDelete') {
            $sortClass = ($sortByAsc) ? 'sorttable_sorted' : 'sorttable_sorted_reverse';
            $thClass = ($headerKey == $sortColumn) ? $sortClass : '';
            $startMain .= '<th class="'.$thClass.'">'.$headerKey.'</th>';
        }
    }
    
    if ($addDeleteColumn) {
        $startMain .= '<th class="sorttable_nosort">X</th>';
    }
    $startMain .= '</tr>';
}

$startMain .= <<< EOT
            </thead>
            <tbody id="tbody">
EOT;
