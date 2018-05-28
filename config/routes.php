<?php
declare(strict_types=1);

use Slim\Http\Request;
use Slim\Http\Response;

// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');

// use as shortcuts for callables in routes
define('NAMESPACE_DOMAIN', 'Domain');

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', NAMESPACE_DOMAIN . '\HomeView:index')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////
