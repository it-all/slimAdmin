<?php
declare(strict_types=1);

use Domain\HomeView;
use Infrastructure\Security\Authentication\AuthenticationView;
use Infrastructure\Security\Authentication\AuthenticationController;
use Infrastructure\Utilities\AdministratorHomeRouteMiddleware;
use Infrastructure\Security\Authentication\GuestMiddleware;
use Infrastructure\Security\Authorization\AuthorizationMiddleware;
use Infrastructure\Security\Authentication\AuthenticationMiddleware;
use Domain\AdminHomeView;
use Entities\Events\EventsListView;
use Entities\Events\EventsController;
use Entities\Administrators\View\AdministratorsListView;
use Entities\Administrators\View\AdministratorsInsertView;
use Entities\Administrators\View\AdministratorsUpdateView;
use Entities\Administrators\AdministratorsController;
use Entities\Roles\View\RolesListView;
use Entities\Roles\View\RolesUpdateView;
use Entities\Roles\View\RolesInsertView;
use Entities\Roles\RolesController;
use Entities\Permissions\View\PermissionsListView;
use Entities\Permissions\View\PermissionsInsertView;
use Entities\Permissions\View\PermissionsUpdateView;
use Entities\Permissions\PermissionsController;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableView;
use Infrastructure\BaseEntity\DatabaseTable\DatabaseTableController;

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', HomeView::class . ':routeIndex')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

/////////////////////////////////////////
// Routes that only non-authenticated users (Guests) can access

$slim->get('/' . $this->config['adminPath'], AuthenticationView::class . ':routeGetLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN);

$slim->post('/' . $config['adminPath'], AuthenticationController::class . ':routePostLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_POST);

/////////////////////////////////////////

// Admin Routes - Routes that only authenticated users access (to end of file)
// Note, if route needs authorization as well, the authorization is added prior to authentication, so that authentication is performed first

// generic admin home, AdministratorHomeRouteMiddleware redirects to user specific home route if configured
$slim->get('/' . $config['adminPath'] . '/home',
    AdminHomeView::class . ':routeIndex')
    ->add(new AdministratorHomeRouteMiddleware($slimContainer))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMIN_HOME_DEFAULT);

// logout
$slim->get('/' . $config['adminPath'] . '/logout', AuthenticationController::class . ':routeGetLogout')
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGOUT);

// events
$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_EVENTS, EventsListView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_EVENTS);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_EVENTS, EventsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_EVENTS . '/reset',
    EventsListView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_EVENTS_RESET);
// end events

// administrators
$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS, AdministratorsListView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS, AdministratorsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/reset', AdministratorsListView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_RESET);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/insert', AdministratorsInsertView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/insert', AdministratorsController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/{' . ROUTEARG_PRIMARY_KEY . '}', AdministratorsUpdateView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/{' . ROUTEARG_PRIMARY_KEY . '}', AdministratorsController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ADMINISTRATORS . '/delete/{' . ROUTEARG_PRIMARY_KEY . '}', AdministratorsController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_DELETE);
// end administrators

// roles
$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES, RolesListView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES, RolesController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/reset', RolesListView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_RESET);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/insert', RolesInsertView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/insert', RolesController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/{' . ROUTEARG_PRIMARY_KEY . '}', RolesUpdateView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE);

$slim->put('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/{' . ROUTEARG_PRIMARY_KEY . '}', RolesController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_ROLES . '/delete/{' . ROUTEARG_PRIMARY_KEY . '}', RolesController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_DELETE);
// end roles

// permissions
$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS, PermissionsListView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS, PermissionsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/reset', PermissionsListView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_RESET);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/insert', PermissionsInsertView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/insert', PermissionsController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/{' . ROUTEARG_PRIMARY_KEY . '}', PermissionsUpdateView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/{' . ROUTEARG_PRIMARY_KEY . '}', PermissionsController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_PERMISSIONS . '/delete/{' . ROUTEARG_PRIMARY_KEY . '}', PermissionsController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_DELETE);
// end permissions

/** Database Tables */
$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/{' . ROUTEARG_DATABASE_TABLE_NAME . '}', DatabaseTableView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/{' . ROUTEARG_DATABASE_TABLE_NAME . '}', DatabaseTableController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/reset/{' . ROUTEARG_DATABASE_TABLE_NAME . '}', DatabaseTableView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_RESET);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/insert/{' . ROUTEARG_DATABASE_TABLE_NAME . '}', DatabaseTableView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_INSERT);

$slim->post('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/insert/{' . ROUTEARG_DATABASE_TABLE_NAME . '}', DatabaseTableController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/update/{' . ROUTEARG_DATABASE_TABLE_NAME . '}/{' . ROUTEARG_PRIMARY_KEY . '}', DatabaseTableView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_UPDATE);

$slim->put('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/update/{' . ROUTEARG_DATABASE_TABLE_NAME . '}/{' . ROUTEARG_PRIMARY_KEY . '}', DatabaseTableController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/' . ROUTEPREFIX_DATABASE_TABLES . '/delete/{' . ROUTEARG_DATABASE_TABLE_NAME . '}/{' . ROUTEARG_PRIMARY_KEY . '}', DatabaseTableController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES_DELETE);
/** end Database Tables */
