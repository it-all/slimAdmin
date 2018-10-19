<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\View;

use Slim\Container;

/**
 * navigation for admin pages
 */
class AdminNavigation
{
    private $nav;
    private $container;

    function __construct(Container $container)
    {
        $this->container = $container;
        $this->setNav();
    }

    /** entire nav without regard to permissions */
    private function setNav()
    {
        $this->nav = [

            'System' => [
                'subSections' => [
                    'Administrators' => [
                        'route' => ROUTE_ADMINISTRATORS,
                        'authorization' => ADMINISTRATORS_VIEW_RESOURCE,
                        'subSections' => [
                            'Insert' => [
                                'route' => ROUTE_ADMINISTRATORS_INSERT,
                                'authorization' => ADMINISTRATORS_INSERT_RESOURCE,
                            ],
                        ]
                    ],

                    'Roles' => [
                        'route' => ROUTE_ADMINISTRATORS_ROLES,
                        'authorization' => ROLES_VIEW_RESOURCE,
                        'subSections' => [
                            'Insert' => [
                                'route' => ROUTE_ADMINISTRATORS_ROLES_INSERT,
                                'authorization' => ROLES_INSERT_RESOURCE,
                            ],
                        ],
                    ],

                    'Permissions' => [
                        'route' => ROUTE_ADMINISTRATORS_PERMISSIONS,
                        'authorization' => PERMISSIONS_VIEW_RESOURCE,
                        'subSections' => [
                            'Insert' => [
                                'route' => ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT,
                                'authorization' => PERMISSIONS_INSERT_RESOURCE,
                            ],
                        ]
                    ],

                    'Events' => [
                        'route' => ROUTE_SYSTEM_EVENTS,
                        'authorization' => SYSTEM_EVENTS_VIEW_RESOURCE,
                    ],

                    'Login Attempts' => [
                        'route' => ROUTE_LOGIN_ATTEMPTS,
                        'authorization' => LOGIN_ATTEMPTS_VIEW_RESOURCE,
                    ],
                ]
            ],
            'Logout' => [
                'route' => ROUTE_LOGOUT,
            ],
        ];

        if (isset($this->container['settings']['adminNav'])) {
            if (!is_array($this->container['settings']['adminNav'])) {
                throw new \Exception("adminNav config must be array");
            }

            $this->nav = array_merge($this->container['settings']['adminNav'], $this->nav);
        }
    }

    /** add nav components as necessary based on logged in administrator role */
    private function getSectionForAdministrator(array $section, string $sectionName): array
    {
        if (isset($section['authorization']) && !$this->container->authorization->isAuthorized($section['authorization'])) {
            return [];
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

                $updatedSubSection = $this->getSectionForAdministrator($subSection, $subSectionName);
                if (count($updatedSubSection) > 0) {
                    $updatedSubSections[$subSectionName] = $updatedSubSection;
                }
            }
        }

        if (count($updatedSubSections) > 0) {
            $updatedSection['subSections'] = $updatedSubSections;
        }

        return $updatedSection;

    }

    public function getNavForAdministrator()
    {
        $nav = []; // rebuild nav sections based on authorization for this user

        foreach ($this->nav as $sectionName => $section) {
            $updatedSection = $this->getSectionForAdministrator($section, $sectionName);
            if (count($updatedSection) > 0) {
                $nav[$sectionName] = $updatedSection;
            }
        }

        return $nav;
    }
}
