<?php
declare(strict_types=1);

define('DOMAIN_NAME', 'example.com');

define('TOP_ROLE', 'owner'); // must match db role

// route prefixes
define('ROUTEPREFIX_ADMIN', 'admin');
define('ROUTEPREFIX_EVENTS', 'events');
define('ROUTEPREFIX_ADMINISTRATORS', 'administrators');
define('ROUTEPREFIX_ROLES', 'roles');
define('ROUTEPREFIX_PERMISSIONS', 'permissions');
define('ROUTEPREFIX_DATABASE_TABLES', 'dbtables');

// authorization resources/permissions, must match permission db records
define('EVENTS_VIEW_RESOURCE', 'Events View');

define('ADMINISTRATORS_VIEW_RESOURCE', 'Administrators View');
define('ADMINISTRATORS_INSERT_RESOURCE', 'Administrators Insert');
define('ADMINISTRATORS_UPDATE_RESOURCE', 'Administrators Update');
define('ADMINISTRATORS_DELETE_RESOURCE', 'Administrators Delete');

define('ROLES_VIEW_RESOURCE', 'Roles View');
define('ROLES_INSERT_RESOURCE', 'Roles Insert');
define('ROLES_UPDATE_RESOURCE', 'Roles Update');
define('ROLES_DELETE_RESOURCE', 'Roles Delete');

define('PERMISSIONS_VIEW_RESOURCE', 'Permissions View');
define('PERMISSIONS_INSERT_RESOURCE', 'Permissions Insert');
define('PERMISSIONS_UPDATE_RESOURCE', 'Permissions Update');
define('PERMISSIONS_DELETE_RESOURCE', 'Permissions Delete');

define('DATABASE_TABLES_VIEW_RESOURCE', 'Database Tables View');

// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');
define('ROUTE_LOGIN', 'authentication.login');
define('ROUTE_LOGIN_POST', 'authentication.post.login');

// admin routes
define('ROUTE_ADMIN_HOME_DEFAULT', ROUTEPREFIX_ADMIN . '.home');
define('ROUTE_LOGOUT', ROUTEPREFIX_ADMIN . 'authentication.logout');

// events
define('ROUTE_EVENTS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_EVENTS.'.index');
define('ROUTE_EVENTS_RESET', ROUTEPREFIX_ADMIN . '.' . ROUTEPREFIX_EVENTS . '.index.reset');
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
// permissions
define('ROUTE_ADMINISTRATORS_PERMISSIONS', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.index');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_RESET', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.index.reset');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.insert');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT_POST', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.post.insert');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.update');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE_PUT', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.put.update');
define('ROUTE_ADMINISTRATORS_PERMISSIONS_DELETE', ROUTEPREFIX_ADMIN . '.'.ROUTEPREFIX_PERMISSIONS.'.delete');
/** database tables */
define('ROUTE_DATABASE_TABLES', ROUTEPREFIX_DATABASE_TABLES);

// nav / permission options without routes
define('NAV_ADMIN_SYSTEM', 'systemNav');

/** route arg names */
define('ROUTEARG_PRIMARY_KEY', 'primaryKey');
define('ROUTEARG_DATABASE_TABLE_NAME', 'tableName');

// EVENT TITLES
define('EVENT_PAGE_NOT_FOUND', '404 Page Not Found');
define('EVENT_MAX_LOGIN_FAULT', 'Maximum unsuccessful login attempts exceeded');
define('EVENT_LOGIN', 'Login');
define('EVENT_LOGIN_FAIL', 'Login Failure');
define('EVENT_LOGIN_REQUIRED', 'Login Required');
define('EVENT_LOGOUT', 'Logout');
define('EVENT_LOGOUT_FAULT', 'Attempted logout for non-logged-in visitor');

define('EVENT_UNAUTHORIZED_ACCESS_ATTEMPT', 'No authorization for resource');
define('CSRF_FAULT', 'CSRF Check Failure');
define('EVENT_QUERY_FAIL', 'Query Failure');
define('EVENT_QUERY_NO_RESULTS', 'Query Results Not Found');
define('EVENT_EMAIL_NOT_FOUND', 'Email Not Found');
define('EVENT_UNALLOWED_ACTION', 'Unallowed Action');


define('EVENT_LIST_VIEW_FILTER_QUERY_FAIL', 'List View Filter Query Failure');

define('EVENT_ADMINISTRATOR_INSERT', 'Inserted Administrator');
define('EVENT_ADMINISTRATOR_UPDATE', 'Updated Administrator');
define('EVENT_ADMINISTRATOR_DELETE', 'Deleted Administrator');
define('EVENT_ADMINISTRATOR_DELETE_FAIL', 'Administrator Deletion Failure');

define('EVENT_PERMISSION_INSERT', 'Inserted Permission');
define('EVENT_PERMISSION_UPDATE', 'Updated Permission');
define('EVENT_PERMISSION_DELETE', 'Deleted Permission');
define('EVENT_PERMISSION_DELETE_FAIL', 'Permission Deletion Failure');

define('EVENT_ROLE_INSERT', 'Inserted Role');
define('EVENT_ROLE_UPDATE', 'Updated Role');
define('EVENT_ROLE_DELETE', 'Deleted Role');
define('EVENT_ROLE_DELETE_FAIL', 'Role Deletion Failure');