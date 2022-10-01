<?php
declare(strict_types=1);

use Templates\Admin\Lists\AdminFilterableListContent;
use Templates\Admin\AdminContent;


// use sortable to get the main html, head css, and body js to feed into the template content object
$alternateListTitle = $listTitle ?? null;
$totalNumResults = $totalNumResults ?? null;
$showAllResultsLink = $showAllLink ?? null;
$sortable = new AdminFilterableListContent($listArray, $headers, $title, $insertLinkInfo, $filterForm, $errorMessage, $addBodyJs, $footers, $alternateListTitle, $totalNumResults, $showAllResultsLink);

$content = new AdminContent($sortable->getMainHtml(), $sortable->getHeadCss(), $sortable->getBodyJs(), $notice, $isLive, $title, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser);

require TEMPLATES_PATH . '/base.php';
