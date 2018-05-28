<?php
declare(strict_types=1);

use Slim\Http\Request;
use Slim\Http\Response;

// GLOBAL ROUTE NAME CONSTANTS
define('ROUTE_HOME', 'home');

// use as shortcuts for callables in routes
define('NAMESPACE_DOMAIN', 'It_All\Slim_Postgres\Domain');
define('NAMESPACE_INFRASTRUCTURE', 'It_All\Slim_Postgres\Infrastructure');

/////////////////////////////////////////
// Routes that anyone can access

$slim->get('/', NAMESPACE_DOMAIN . '\HomeView:index')->setName(ROUTE_HOME);

// remainder of front end pages to go here

/////////////////////////////////////////

//$slim->get('/', function (Request $request, Response $response, $args) {
//    return $this->view->render($response, 'profile.html', [
////        'name' => $args['name']
//    ]);
//});
//
//$slim->get('/test/{name}', function ($request, $response, $args) {
//    return $this->view->render($response, 'profile.php', [
//        'name' => $args['name']
//    ]);
//});