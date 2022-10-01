<?php
declare(strict_types=1);

use Templates\Admin\AdminContent;

$mainHtml = '<div id="simpleContainer"><h1>'.$title.'</h1>'.$formHtml.'</div>';
$headCss = '<link href="'.CSS_DIR_PATH.'/adminFlexSimple.css" rel="stylesheet" type="text/css">';
$bodyJs = '<script type="text/javascript" src="'.JS_DIR_PATH.'/uiHelper.js"></script>';
// set $hideFocus true to have no form field focus
if ( (!isset($hideFocus) || !$hideFocus) && mb_strlen($focusFieldId) > 0) {
    $bodyJs .= '<script type="text/javascript">window.onload = document.getElementById(\''.$focusFieldId.'\').focus();</script>';
}

$debugString = $debug ?? null;
$content = new AdminContent($mainHtml, $headCss, $bodyJs, $notice, $isLive, $title, $domainName, $businessName, $businessDba, $authentication, $navigationItems, $routeParser, $debugString);

require TEMPLATES_PATH . '/base.php';
