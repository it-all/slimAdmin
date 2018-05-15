<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

/* sets up configuration, error handling, database connection, session */
//require __DIR__ . '/../../vendor/it-all/spaghettify/App.php';

$app = new Spaghettify\App(require __DIR__ . '/../config/env.php');
$app->run();

//
//// Instantiate Slim PHP
//$settings = require __DIR__ . '/../config/slim3/settings.php';
//$slim = new \Slim\App($settings);
//
//$container = $slim->getContainer();
//
//// Set up Slim dependencies
//require __DIR__ . '/../config/slim3/dependencies.php';
//
//// Remove Slim's error handling
//unset($container['errorHandler']);
//unset($container['phpErrorHandler']);
//
//// Global middleware registration
//require __DIR__ . '/../config/slim3/middleware.php';
//
//// Register routes
//require __DIR__ . '/../config/slim3/routes.php';
//
//$slim->run();
