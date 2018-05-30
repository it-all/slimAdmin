<?php
declare(strict_types=1);

$header = '<header id="adminPageHeader">'; 
if ($authentication->check()) {
    require 'nav.php';
    $header .= <<< EOT
<nav id="adminNav">
    <input type="checkbox" id="toggle-nav">
    <label id="toggle-nav-label" for="toggle-nav">
        <img src="/assets/images/admin/menu-icon.png" width="20" height="20">
    </label>

    <div id="navOverlay">
        $nav
    </div>
</nav>
EOT;

}

$header .= '<div id="adminPageHeaderLogo">';
$header .= '<a href="/">'.$businessDba.'</a>';
if (!$isLive) {
    $header .= '<span style="color: grey;">{LOCAL}</span>';
}
$header .= '</div>';

if ($authentication->check()) {
    $header .= '<div id="adminPageHeaderNotice">';
    $adminNotice = $_SESSION[\SlimPostgres\App::SESSION_KEY_ADMIN_NOTICE];
    if (isset($adminNotice)) {
        $header .= '<span class="'.$adminNotice[1].'">&raquo; '.$adminNotice[0].' &laquo;</span>';
    }
    $header .= '</div>';
    $header .= '<div id="adminPageHeaderGreeting">
        Hello '.$authentication->getUserName().'
            [<a href="authentication.logout">logout</a>]
        </div>';
}

$header .= '</header>';

if (isset($debug) && strlen($debug) > 0) {
    $header .= '<div id="debug">$debug</div>';
}
