<?php
declare(strict_types=1);

namespace Domain;
use Slim\Container;

/**
 * navigation for admin pages
 */
class NavAdmin
{
    private $nav;
    private $container;

    function __construct(Container $container)
    {
        $this->container = $container;
        $this->setNav();
    }

    // todo set nav in config or somewhere else
    private function setNav()
    {
        $router = $this->container->router;

        $this->nav = [

            'System' => [
                'subSections' => [
                    'Events' => [
                        'link' => $router->pathFor(ROUTE_SYSTEM_EVENTS_RESET),
                    ],

                    'Administrators' => [
                        'link' => $router->pathFor(ROUTE_ADMIN_ADMINISTRATORS_RESET),
                        'subSections' => [

                            'Insert' => [
                                'link' => $router->pathFor(ROUTE_ADMIN_ADMINISTRATORS_INSERT),
                            ],

                            'Roles' => [
                                'link' => $router->pathFor(ROUTE_ADMIN_ROLES),
                                'subSections' => [
                                    'Insert' => [
                                        'link' => $router->pathFor(ROUTE_ADMIN_ROLES_INSERT),
                                    ]
                                ],
                            ],

                            'Login Attempts' => [
                                'link' => $router->pathFor(ROUTE_LOGIN_ATTEMPTS),
                            ],
                        ]
                    ]
                ]
            ],
            'Logout' => [
                'link' => $router->pathFor(ROUTE_LOGOUT)
            ]
        ];
    }

    // precedence:
    // 1. directly set by minimumPermissions key in the section
    // 2. by section link
    // 3. by section name
    private function getSectionPermissions(array $section, string $sectionName)
    {
        if (isset($section['permissions'])) {
            return $section['permissions'];
        }

        if (isset($section['link'])) {
            return $this->container->authorization->getPermissions($section['link']);
        }

        // by nav section - ie NAV_ADMIN_SYSTEM
        if (!$sectionNameConstant = constant('NAV_ADMIN_'.strtoupper(str_replace(" ", "_", $sectionName)))) {
           throw new \Exception("Undefined admin nav constant for ".'NAV_ADMIN_'.strtoupper(str_replace(" ", "_", $sectionName)));
        }

        return $this->container->authorization->getPermissions($sectionNameConstant);

    }

    private function getSectionForUserRecurs(array $section, string $sectionName)
    {
        // if there are section permissions and they are not met
        if ($permissions = $this->getSectionPermissions($section, $sectionName)) {
            if (!$this->container->authorization->check($permissions)) {
                return false;
            }
        }

        // rebuild based on permissions
        $updatedSection = [];
        foreach ($section as $key => $value) {
            if ($key != 'subSections') {
                $updatedSection[$key] = $value;
            }
        }

        $updatedSubSections = [];
        if (isset($section['subSections'])) {
            foreach ($section['subSections'] as $subSectionName => $subSection) {

                $updatedSubSection = $this->getSectionForUserRecurs($subSection, $subSectionName);
                // CAREFUL, empty arrays evaluate to false
                if ($updatedSubSection !== false) {
                    $updatedSubSections[$subSectionName] = $updatedSubSection;
                }
            }
        }

        if (count($updatedSubSections) > 0) {
            $updatedSection['subSections'] = $updatedSubSections;
        }

        return $updatedSection;

    }

    public function getNavForUser()
    {
        $nav = []; // rebuild nav sections based on authorization for this user

        foreach ($this->nav as $sectionName => $section) {
            $updatedSection = $this->getSectionForUserRecurs($section, $sectionName);
            // CAREFUL, empty arrays evaluate to false
            if ($updatedSection !== false) {
                $nav[$sectionName] = $updatedSection;
            }
        }

        return $nav;
    }
}
