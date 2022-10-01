<?php
declare(strict_types=1);

namespace Templates\Admin;

use Infrastructure\Security\Authorization\AuthorizationService;
use Slim\Interfaces\RouteParserInterface;

class NavContent 
{
    private $navMenu; // string

    public function __construct(?array $navigationItems, RouteParserInterface $routeParser, string $domainName)
    {
        $this->setNavMenu($navigationItems, $routeParser, $domainName);
    }

    /** either a link or plain text */
    private function getNavItem(string $navText, array $navItem, RouteParserInterface $routeParser): string 
    {
        if (isset($navItem['route'])) {
            $href = isset($navItem['args']) ? $routeParser->urlFor($navItem['route'], $navItem['args']) : $routeParser->urlFor($navItem['route']);
            return '<a href="' . $href . '">' . $navText . '</a>';
        } else {
            return $navText;
        }
    }

    private function getNavSection(string $key, array $navItem, RouteParserInterface $routeParser): string
    {
        $navSection = '<li>' . $this->getNavItem($key, $navItem, $routeParser);

        if (isset($navItem['subSections'])) {
            $navSection .= '<a href="#" onclick="toggleDisplay(getElementById(\''.$key.'\'));togglePlusMinus(this);">+</a>';
            $navSection .= '<ul class="adminNavSubSection" id="'.$key.'">';
            foreach ($navItem['subSections'] as $subKey => $subNavItem) {
                $navSection .= $this->getNavSection($subKey, $subNavItem, $routeParser);
            }
            $navSection .= '</ul>';
        }

        $navSection .= '</li>';
        return $navSection;
    }

    private function setNavMenu(?array $navigationItems, RouteParserInterface $routeParser, string $domainName) 
    {
        $this->navMenu = '<ul>';
        if ($navigationItems != null) {
            foreach ($navigationItems as $navKey => $navItem) {
                $this->navMenu .= $this->getNavSection($navKey, $navItem, $routeParser);
            }
        }
        $this->navMenu .= '</ul>';
    }

    public function getNavMenu(): string 
    {
        return $this->navMenu;
    }
}
