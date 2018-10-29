<?php

use Slim\Router;

function navSection($key, $navItem, Router $router)
{
    $navSection = '<li>';

    $navSection .= (isset($navItem['route'])) ? '<a href="'.$router->pathFor($navItem['route']).'">'.$key.'</a>' : $key;
    if (isset($navItem['subSections']))     {
        $navSection .= '<a href="#" onclick="toggleDisplay(getElementById(\''.$key.'\'));togglePlusMinus(this);">+</a>';
        $navSection .= '<ul class="adminNavSubSection" id="'.$key.'">';
        foreach ($navItem['subSections'] as $subKey => $subNavItem) {
            $navSection .= navSection($subKey, $subNavItem, $router);
        }
        $navSection .= '</ul>';
    }

    $navSection .= '</li>';
    return $navSection;
}

$nav = '';

if (isset($navigationItems)) {
    $nav .= '<ul>';
    foreach ($navigationItems as $navKey => $navItem) {
        $nav .= navSection($navKey, $navItem, $router);
    }
    $nav .= '</ul>';
}
