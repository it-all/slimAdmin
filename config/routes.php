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
use Entities\Events\EventsView;
use Entities\Events\EventsController;
use Entities\Administrators\View\AdministratorsView;
use Entities\Administrators\AdministratorsController;
use Entities\Roles\RolesView;
use Entities\Roles\RolesController;
use Entities\Permissions\View\PermissionsView;
use Entities\Permissions\PermissionsController;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableView;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableViewChild;

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', HomeView::class . ':routeIndex')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

/////////////////////////////////////////
// Routes that only non-authenticated users (Guests) can access

$slim->get('/' . $config['adminPath'], AuthenticationView::class . ':routeGetLogin')
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

$slim->get('/' . $config['adminPath'] . '/Events', EventsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_EVENTS);

$slim->post('/' . $config['adminPath'] . '/Events', EventsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/Events/reset',
    EventsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, EVENTS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_EVENTS_RESET);
// end events

// administrators

$slim->get('/' . $config['adminPath'] . '/administrators', AdministratorsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS);

$slim->post('/' . $config['adminPath'] . '/administrators', AdministratorsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/administrators/reset', AdministratorsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_RESET);

$slim->get('/' . $config['adminPath'] . '/administrators/insert', AdministratorsView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT);

$slim->post('/' . $config['adminPath'] . '/administrators/insert', AdministratorsController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/administrators/{'.ROUTEARG_PRIMARY_KEY.'}', AdministratorsView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/administrators/{'.ROUTEARG_PRIMARY_KEY.'}', AdministratorsController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/administrators/delete/{'.ROUTEARG_PRIMARY_KEY.'}', AdministratorsController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, ADMINISTRATORS_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_DELETE);
// end administrators

// roles

$slim->get('/' . $config['adminPath'] . '/roles', RolesView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES);

$slim->post('/' . $config['adminPath'] . '/roles', RolesController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/roles/reset', RolesView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_RESET);

$slim->get('/' . $config['adminPath'] . '/roles/insert', RolesView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT);

$slim->post('/' . $config['adminPath'] . '/roles/insert', RolesController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/roles/{'.ROUTEARG_PRIMARY_KEY.'}', RolesView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE);

$slim->put('/' . $config['adminPath'] . '/roles/{'.ROUTEARG_PRIMARY_KEY.'}', RolesController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/roles/delete/{'.ROUTEARG_PRIMARY_KEY.'}', RolesController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, ROLES_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_DELETE);
// end roles

// permissions

$slim->get('/' . $config['adminPath'] . '/permissions', PermissionsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS);

$slim->post('/' . $config['adminPath'] . '/permissions', PermissionsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/permissions/reset', PermissionsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_RESET);

$slim->get('/' . $config['adminPath'] . '/permissions/insert', PermissionsView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT);

$slim->post('/' . $config['adminPath'] . '/permissions/insert', PermissionsController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_INSERT_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/permissions/{'.ROUTEARG_PRIMARY_KEY.'}', PermissionsView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/permissions/{'.ROUTEARG_PRIMARY_KEY.'}', PermissionsController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_UPDATE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/permissions/delete/{'.ROUTEARG_PRIMARY_KEY.'}', PermissionsController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, PERMISSIONS_DELETE_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_PERMISSIONS_DELETE);

// end permissions

/** Database Tables */

$slim->get('/' . $config['adminPath'] . '/'.ROUTEPREFIX_DATABASE_TABLES.'/{'.ROUTEARG_DATABASE_TABLE_NAME.'}', DatabaseTableViewChild::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, DATABASE_TABLES_VIEW_RESOURCE))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_DATABASE_TABLES);

/** end Database Tables */
