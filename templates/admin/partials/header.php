<?php
declare(strict_types=1);

$header = '<header id="adminPageHeader">'; 
if ($authentication->isAuthenticated()) {
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

if ($authentication->isAuthenticated()) {
    $header .= '<div id="adminPageHeaderNotice">';
    if (isset($_SESSION[\SlimPostgres\App::SESSION_KEY_ADMIN_NOTICE])) {
        $header .= '<span class="'.$_SESSION[\SlimPostgres\App::SESSION_KEY_ADMIN_NOTICE][1].'">&raquo; '.$_SESSION[\SlimPostgres\App::SESSION_KEY_ADMIN_NOTICE][0].' &laquo;</span>';
        unset($_SESSION[\SlimPostgres\App::SESSION_KEY_ADMIN_NOTICE]);
    }
    $header .= '</div>';
    $header .= '<div id="adminPageHeaderGreeting">
        Hello '.$authentication->getAdministratorName().'
            [<a href="'.$router->pathFor(ROUTE_LOGOUT).'">logout</a>]
        </div>';
}

$header .= '</header>';

if (isset($debug) && mb_strlen($debug) > 0) {
    $header .= '<div id="debug">$debug</div>';
}
