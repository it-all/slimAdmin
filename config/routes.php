<?php
declare(strict_types=1);

use SlimPostgres\Security\Authentication\GuestMiddleware;
use SlimPostgres\Security\Authorization\AuthorizationMiddleware;
use SlimPostgres\Security\Authentication\AuthenticationMiddleware;

$administratorPermissions = $config['slim']['settings']['authorization']['administratorPermissions'];

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', NAMESPACE_DOMAIN . '\HomeView:index')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

/////////////////////////////////////////
// Routes that only non-authenticated users (Guests) can access

$slim->get('/' . $config['adminPath'],
    NAMESPACE_SECURITY.'\Authentication\AuthenticationView:getLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN);

$slim->post('/' . $config['adminPath'],
    NAMESPACE_SECURITY.'\Authentication\AuthenticationController:postLogin')
    ->add(new GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_POST);
/////////////////////////////////////////

// Admin Routes - Routes that only authenticated users access (to end of file)
// Note, if route needs authorization as well, the authorization is added prior to authentication, so that authentication is performed first

// admin home
$slim->get('/' . $config['adminPath'] . '/home',
    NAMESPACE_DOMAIN.'\AdminHomeView:index')
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMIN_HOME_DEFAULT);

// logout
$slim->get('/' . $config['adminPath'] . '/logout',
    NAMESPACE_SECURITY.'\Authentication\AuthenticationController:getLogout')
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGOUT);

// system events
$slim->get('/' . $config['adminPath'] . '/systemEvents',
    NAMESPACE_SYSTEM_EVENTS . '\SystemEventsView:index')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_SYSTEM_EVENTS);

$slim->post('/' . $config['adminPath'] . '/systemEvents',
    NAMESPACE_SYSTEM_EVENTS . '\SystemEventsController:postIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/systemEvents/reset',
    NAMESPACE_SYSTEM_EVENTS . '\SystemEventsView:indexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_SYSTEM_EVENTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_SYSTEM_EVENTS_RESET);
// end system events

// logins
$slim->get('/' . $config['adminPath'] . '/logins',
    NAMESPACE_LOGINS . '\LoginsView:index')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_ATTEMPTS);

$slim->post('/' . $config['adminPath'] . '/logins',
    NAMESPACE_LOGINS . '\LoginsController:postIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/logins/reset',
    NAMESPACE_LOGINS . '\LoginsView:indexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_LOGIN_ATTEMPTS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_ATTEMPTS_RESET);

// administrators
$slim->get('/' . $config['adminPath'] . '/administrators',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsView:index')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS);

$slim->post('/' . $config['adminPath'] . '/administrators',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsController:postIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/administrators/reset',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsView:indexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_RESET]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_RESET);

$slim->get('/' . $config['adminPath'] . '/administrators/insert',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsView:getInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_INSERT]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT);

$slim->post('/' . $config['adminPath'] . '/administrators/insert',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsController:postInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_INSERT]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/administrators/{primaryKey}',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsView:getUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_UPDATE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE);

$slim->put('/' . $config['adminPath'] . '/administrators/{primaryKey}',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsController:putUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_UPDATE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/administrators/delete/{primaryKey}',
    NAMESPACE_ADMINISTRATORS . '\AdministratorsController:getDelete')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_DELETE]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_DELETE);
// end administrators

// roles
$slim->get('/' . $config['adminPath'] . '/roles',
    NAMESPACE_ROLES . '\RolesView:index')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES);

$slim->post('/' . $config['adminPath'] . '/roles',
    NAMESPACE_ROLES . '\RolesController:postIndexFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer));

$slim->get('/' . $config['adminPath'] . '/roles/reset',
    NAMESPACE_ROLES . '\RolesView:indexResetFilter')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_RESET);

$slim->get('/' . $config['adminPath'] . '/roles/insert',
    NAMESPACE_ROLES . '\RolesView:getInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT);

$slim->post('/' . $config['adminPath'] . '/roles/insert',
    NAMESPACE_ROLES . '\RolesController:postInsert')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_INSERT_POST);

$slim->get('/' . $config['adminPath'] . '/roles/{primaryKey}',
    NAMESPACE_ROLES . '\RolesView:getUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE);

$slim->put('/' . $config['adminPath'] . '/roles/{primaryKey}',
    NAMESPACE_ROLES . '\RolesController:putUpdate')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_UPDATE_PUT);

$slim->get('/' . $config['adminPath'] . '/roles/delete/{primaryKey}',
    NAMESPACE_ROLES . '\RolesController:getDelete')
    ->add(new AuthorizationMiddleware($slimContainer, $administratorPermissions[ROUTE_ADMINISTRATORS_ROLES]))
    ->add(new AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMINISTRATORS_ROLES_DELETE);
// end roles


