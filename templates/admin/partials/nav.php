<?php

use Slim\Router;

/** either a link or plain text */
function getNavItem(string $navText, array $navItem, Router $router): string 
{
    if (isset($navItem['route'])) {
        $href = isset($navItem['args']) ? $router->pathFor($navItem['route'], $navItem['args']) : $router->pathFor($navItem['route']);
        return '<a href="' . $href . '">' . $navText . '</a>';
    } else {
        return $navText;
    }
}

function navSection(string $key, array $navItem, Router $router): string
{
    $navSection = '<li>' . getNavItem($key, $navItem, $router);

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
