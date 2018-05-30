<?php
declare(strict_types=1);

$header = '<header id="adminPageHeader">'; 
if ($authentication->check()) {
    $header .= <<< EOT
<nav id="adminNav">
    <input type="checkbox" id="toggle-nav">
    <label id="toggle-nav-label" for="toggle-nav">
        <img src="/images/admin/menu-icon.png" width="20" height="20">
    </label>

    <div id="navOverlay">
        NAV
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
    if (isset($_SESSION[\SlimPostgres\App::SESSION_KEYS['adminNotice']])) {
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
