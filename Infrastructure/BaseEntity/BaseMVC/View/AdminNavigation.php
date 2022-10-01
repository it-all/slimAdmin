<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Psr\Container\ContainerInterface as Container;
use Infrastructure\Database\Postgres;

/**
 * navigation for admin pages
 * defines the system nav and merges with nav set in config
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
                        'route' => ROUTE_EVENTS,
                        'authorization' => EVENTS_VIEW_RESOURCE,
                        'subSections' => [
                            'Types' => [
                                'route' => ROUTE_DATABASE_TABLES,
                                'args' => [ROUTEARG_DATABASE_TABLE_NAME => 'event_types'],
                                'authorization' => EVENTS_VIEW_RESOURCE,
                            ],
                        ]
                    ],

                    'Database' => [
                        'authorization' => DATABASE_TABLES_VIEW_RESOURCE,
                        'subSections' => $this->getDatabaseTablesSection()
                    ],

                ]
            ],
            'Logout' => [
                'route' => ROUTE_LOGOUT,
            ],
        ];

        if (isset($this->container->get('settings')['adminNav'])) {
            if (!is_array($this->container->get('settings')['adminNav'])) {
                throw new \Exception("adminNav config must be array");
            }

            $this->nav = array_merge($this->container->get('settings')['adminNav'], $this->nav);
        }
    }

    /** table view is allowed unless settings[table] is set false */
    private function isDatabaseTableViewAllowed(string $table) : bool 
    {
        $allowedTables = 'all'; // init
        if (isset($this->container->get('settings')['orm']['administrators'][$this->container->get('authentication')->getAdministratorUsername()])) {
            $allowedTables = $this->container->get('settings')['orm']['administrators'][$this->container->get('authentication')->getAdministratorUsername()];
        }

        return !in_array($table, $this->container->get('settings')['orm']['disallowTables']) && ($allowedTables == 'all' || in_array($table, $allowedTables));
    }

    private function getDatabaseTablesSection(): array 
    {
        $section = [];
        $tables = Postgres::getSchemaTables();

        foreach ($tables as $table) {
            if ($this->isDatabaseTableViewAllowed($table)) {
                $section[$table] = [
                    'route' => ROUTE_DATABASE_TABLES,
                    'args' => [ROUTEARG_DATABASE_TABLE_NAME => $table]
                ];    
            }
        }
        return $section;
    }

    /** add nav components as necessary based on logged in administrator role */
    private function getSectionForAdministrator(array $section, string $sectionName): array
    {
        if (isset($section['authorization']) && !$this->container->get('authorization')->isAuthorized($section['authorization'])) {
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
