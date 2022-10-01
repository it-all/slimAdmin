<?php
declare(strict_types=1);

use Templates\Admin\AdminContent;

// the base admin template

if (!isset($mainHtml)) {
    throw new \Exception("mainHtml must be set");
}

$cssFile = isset($headCss) ? $headCss : 'adminFlexSimple.css';
$headCss = '<link href="'.CSS_DIR_PATH.'/'.$cssFile.'" rel="stylesheet" type="text/css">';
$bodyJs = '<script type="text/javascript" src="'.JS_DIR_PATH.'/uiHelper.js"></script>';
if (isset($bodyJsAdd)) {
    $bodyJs .= $bodyJsAdd;
}
$titleString = $title ?? 'Admin';
$debugString = $debug ?? null;
$content = new AdminContent($mainHtml, $headCss, $bodyJs, $notice, $isLive, $titleString, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser, $debugString);

require TEMPLATES_PATH . '/base.php';
