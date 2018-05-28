<?php
declare(strict_types=1);

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
(new \SlimPostgres\App())->run();
