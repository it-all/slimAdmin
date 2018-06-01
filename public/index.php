<?php
declare(strict_types=1);

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';

// admin route prefixes
define('ROUTEPREFIX_ADMIN', 'admin');
define('ROUTEPREFIX_ADMINISTRATORS', 'administrators');
define('ROUTEPREFIX_ROLES', 'roles');

// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');
define('ROUTE_LOGIN', 'authentication.login');
define('ROUTE_LOGIN_POST', 'authentication.post.login');

// admin routes
define('ROUTE_ADMIN_HOME_DEFAULT', ROUTEPREFIX_ADMIN . '.home');
define('ROUTE_LOGOUT', ROUTEPREFIX_ADMIN . 'authentication.logout');

// login attempts
define('ROUTE_LOGIN_ATTEMPTS', ROUTEPREFIX_ADMIN . '.logins.index');
define('ROUTE_LOGIN_ATTEMPTS_RESET', ROUTEPREFIX_ADMIN . '.logins.index.reset');
// system events
define('ROUTE_SYSTEM_EVENTS', ROUTEPREFIX_ADMIN . '.systemEvents.index');
define('ROUTE_SYSTEM_EVENTS_RESET', ROUTEPREFIX_ADMIN . '.systemEvents.index.reset');
// administrators
define('ROUTE_ADMINISTRATORS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.index');
define('ROUTE_ADMINISTRATORS_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.index.reset');
define('ROUTE_ADMINISTRATORS_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.insert');
define('ROUTE_ADMINISTRATORS_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.post.insert');
define('ROUTE_ADMINISTRATORS_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.update');
define('ROUTE_ADMINISTRATORS_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.put.update');
define('ROUTE_ADMINISTRATORS_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ADMINISTRATORS.'.delete');
// roles
define('ROUTE_ADMINISTRATORS_ROLES', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.index');
define('ROUTE_ADMINISTRATORS_ROLES_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.index.reset');
define('ROUTE_ADMINISTRATORS_ROLES_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.insert');
define('ROUTE_ADMINISTRATORS_ROLES_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.post.insert');
define('ROUTE_ADMINISTRATORS_ROLES_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.update');
define('ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.put.update');
define('ROUTE_ADMINISTRATORS_ROLES_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_ROLES.'.delete');

// todo where should these live?
// nav / permission options without routes
define('NAV_ADMIN_SYSTEM', '.'.'system');
define('NAV_ADMIN_DESIGNERS', '.'.'designers');
define('NAV_ADMIN_MARKETING', '.'.'marketing');
define('NAV_ADMIN_TESTIMONIALS', '.'.'testimonials');
define('NAV_ADMIN_STAFFING', '.'.'staffing');

// use as shortcuts for callables in routes
define('NAMESPACE_DOMAIN', 'Domain');
define('NAMESPACE_SECURITY', 'SlimPostgres\Security');
define('NAMESPACE_SYSTEM_EVENTS', 'SlimPostgres\SystemEvents');
define('NAMESPACE_ADMINISTRATORS', 'Domain\Administrators');
define('NAMESPACE_LOGINS', 'Domain\Administrators\Logins');
define('NAMESPACE_ROLES', 'Domain\Administrators\Roles');


(new \SlimPostgres\App())->run();
