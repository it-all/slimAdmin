<?php
declare(strict_types=1);

$htmlHeadCss = '<link href="/css/adminFlexSimple.css" rel="stylesheet" type="text/css">';
require 'partials/header.php';
require 'partials/footer.php';
$htmlBodyContent = $header;
$htmlBodyContent .= '<main>Welcome to the admin.</main>';
$htmlBodyJs = '<script type="text/javascript" src="/js/uiHelper.js"></script>';

require APPLICATION_ROOT_DIRECTORY . '/templates/base.php';
