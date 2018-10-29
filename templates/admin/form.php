<?php
declare(strict_types=1);

require 'partials/header.php';
require 'partials/footer.php';

$htmlHeadCss = '<link href="/css/adminFlexSimple.css" rel="stylesheet" type="text/css">';
$htmlBodyContent = $header;
$htmlBodyContent .= '<main><div id="simpleContainer"><h1>'.$title.'</h1>'.$form->generate().'</div></main>';
$htmlBodyContent .= $footer;
$htmlBodyJs = '<script type="text/javascript" src="/js/uiHelper.js"></script>';

// set $hideFocus true to have no form field focus
if ( (!isset($hideFocus) || !$hideFocus) && mb_strlen($form->getFocusFieldId()) > 0) {
    $htmlBodyJs .= '<script type="text/javascript">window.onload = document.getElementById(\''.$form->getFocusFieldId().'\').focus();</script>';
}

require APPLICATION_ROOT_DIRECTORY . '/templates/base.php';
