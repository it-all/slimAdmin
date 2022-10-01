<?php
declare(strict_types=1);

use Infrastructure\SlimAdmin;

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

$SlimAdmin = SlimAdmin::getInstance();
$SlimAdmin->run();
