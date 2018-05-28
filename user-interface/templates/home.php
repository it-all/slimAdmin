<?php
declare(strict_types=1);

$title = $businessName;
$htmlHeadCss = '<link href="/css/frontend.css" rel="stylesheet" type="text/css">';
$noticeDiv = (isset($notice)) ? '<div class="'.$notice[1].'">'.$notice[0].'</div>' : '';
$htmlBodyContent = <<< EOL
    <main>
        <h1>Homepage of $businessName</h1>
        $noticeDiv
    </main>
EOL;
require 'base.php';
