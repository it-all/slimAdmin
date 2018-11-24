<?php
declare(strict_types=1);

require 'partials/header.php';
require 'partials/footer.php';

$htmlHeadCss = '<link href="/css/adminFlexSimple.css" rel="stylesheet" type="text/css">';
$htmlBodyContent = $header;
$htmlBodyContent .= '<main>Welcome to the default home page of the admin.</main>';
$htmlBodyContent .= $footer;
$htmlBodyJs = '<script type="text/javascript" src="/js/uiHelper.js"></script>';

require APPLICATION_ROOT_DIRECTORY . '/templates/base.php';
