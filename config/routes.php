<?php
declare(strict_types=1);

// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');
define('ROUTE_LOGIN', 'authentication.login');
define('ROUTE_LOGIN_POST', 'authentication.post.login');
// admin routes
define('ROUTE_ADMIN_HOME_DEFAULT', 'admin.home');
define('ROUTE_LOGOUT', 'authentication.logout');


// use as shortcuts for callables in routes
define('NAMESPACE_DOMAIN', 'Domain');
define('NAMESPACE_SECURITY', '\SlimPostgres\Security');

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', NAMESPACE_DOMAIN . '\HomeView:index')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

/////////////////////////////////////////
// Routes that only non-authenticated users (Guests) can access

$slim->get('/' . $config['adminPath'],
    NAMESPACE_SECURITY.'\Authentication\AuthenticationView:getLogin')
    ->add(new \SlimPostgres\Security\Authentication\GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN);

$slim->post('/' . $config['adminPath'],
    NAMESPACE_SECURITY.'\Authentication\AuthenticationController:postLogin')
    ->add(new \SlimPostgres\Security\Authentication\GuestMiddleware($slimContainer))
    ->setName(ROUTE_LOGIN_POST);
/////////////////////////////////////////

// Admin Routes - Routes that only authenticated users access (to end of file)
// Note, if route needs authorization as well, the authorization is added prior to authentication, so that authentication is performed first

$slim->get('/' . $config['adminPath'] . '/home',
    NAMESPACE_DOMAIN.'\AdminHomeView:index')
    ->add(new \SlimPostgres\Security\Authentication\AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_ADMIN_HOME_DEFAULT);

$slim->get('/' . $config['adminPath'] . '/logout',
    NAMESPACE_SECURITY.'\Authentication\AuthenticationController:getLogout')
    ->add(new \SlimPostgres\Security\Authentication\AuthenticationMiddleware($slimContainer))
    ->setName(ROUTE_LOGOUT);

