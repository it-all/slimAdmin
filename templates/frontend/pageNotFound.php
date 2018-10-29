<?php
declare(strict_types=1);

Infrastructure\SlimPostgres

$title = $businessName;
$htmlHeadCss = '<link href="/css/frontend.css" rel="stylesheet" type="text/css">';

if ((isset($_SESSION[SlimPostgres::SESSION_KEY_NOTICE]))) {
    $noticeDiv = '<div class="'.$_SESSION[SlimPostgres::SESSION_KEY_NOTICE][1].'">'.$_SESSION[SlimPostgres::SESSION_KEY_NOTICE][0].'</div>';
    unset($_SESSION[SlimPostgres::SESSION_KEY_NOTICE]);
} else {
    $noticeDiv = '';
}

$htmlBodyContent = <<< EOL
    <main>
        <h1>Homepage of $businessName</h1>
        $noticeDiv
    </main>
EOL;
require APPLICATION_ROOT_DIRECTORY . '/templates/base.php';
