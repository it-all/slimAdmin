<?php
declare(strict_types=1);

use Domain\HomeView;
use SlimPostgres\Security\Authentication\AuthenticationView;
use SlimPostgres\Security\Authentication\AuthenticationController;
use SlimPostgres\Security\Authentication\GuestMiddleware;
use SlimPostgres\Security\Authorization\AuthorizationMiddleware;
use SlimPostgres\Security\Authentication\AuthenticationMiddleware;
use Domain\AdminHomeView;
use SlimPostgres\SystemEvents\SystemEventsView;
use SlimPostgres\SystemEvents\SystemEventsController;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsView;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsController;
use SlimPostgres\Administrators\AdministratorsView;
use SlimPostgres\Administrators\AdministratorsController;
use SlimPostgres\Administrators\Roles\RolesView;
use SlimPostgres\Administrators\Roles\RolesController;

$administratorPermissions = $config['authorization']['administratorPermissions'];

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', HomeView::class . ':routeIndex')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

/////////////////////////////////////////
// Routes that only non-authenticated users (Guests) can access

$slim->get('/' . $config['adminPath'], AuthenticationView::class . ':getLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN);

$slim->post('/' . $config['adminPath'], AuthenticationController::class . ':routePostLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_POST);
/////////////////////////////////////////

// Admin Routes - Routes that only authenticated users access (to end of file)
// Note, if route needs authorization as well, the authorization is added prior to authentication, so that authentication is performed first

// admin home
$slim->get('/' . $config['adminPath'] . '/home',
    AdminHomeView::class . ':routeIndex')
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMIN_HOME_DEFAULT);

// logout
$slim->get('/' . $config['adminPath'] . '/logout', AuthenticationController::class . ':routeGetLogout')
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGOUT);

// system events
$slim->get('/' . $config['adminPath'] . '/systemEvents', SystemEventsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_SYSTEM_EVENTS);

$slim->post('/' . $config['adminPath'] . '/systemEvents', SystemEventsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/systemEvents/reset',
    SystemEventsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_SYSTEM_EVENTS_RESET);
// end system events

// login attempts
$slim->get('/' . $config['adminPath'] . '/logins', LoginAttemptsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_ATTEMPTS);

$slim->post('/' . $config['adminPath'] . '/logins', LoginAttemptsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/logins/reset',
    LoginAttemptsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_ATTEMPTS_RESET);

// administrators
$slim->get('/' . $config['adminPath'] . '/administrators', AdministratorsView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS);

$slim->post('/' . $config['adminPath'] . '/administrators', AdministratorsController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/administrators/reset', AdministratorsView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_RESET]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_RESET);

$slim->get('/' . $config['adminPath'] . '/administrators/insert', AdministratorsView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_INSERT]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT);

$slim->post('/' . $config['adminPath'] . '/administrators/insert', AdministratorsController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_INSERT]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/administrators/{primaryKey}', AdministratorsView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_UPDATE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/administrators/{primaryKey}', AdministratorsController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_UPDATE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/administrators/delete/{primaryKey}', AdministratorsController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_DELETE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_DELETE);
// end administrators

// roles
$slim->get('/' . $config['adminPath'] . '/roles', RolesView::class . ':routeIndex')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES);

$slim->post('/' . $config['adminPath'] . '/roles', RolesController::class . ':routePostIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/roles/reset', RolesView::class . ':routeIndexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_RESET);

$slim->get('/' . $config['adminPath'] . '/roles/insert', RolesView::class . ':routeGetInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT);

$slim->post('/' . $config['adminPath'] . '/roles/insert', RolesController::class . ':routePostInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/roles/{primaryKey}', RolesView::class . ':routeGetUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE);

$slim->put('/' . $config['adminPath'] . '/roles/{primaryKey}', RolesController::class . ':routePutUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/roles/delete/{primaryKey}', RolesController::class . ':routeGetDelete')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_DELETE);
// end roles
