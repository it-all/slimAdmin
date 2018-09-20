<?php
declare(strict_types=1);

use \SlimPostgres\Administrators\AdministratorsMapper;
use \SlimPostgres\Administrators\Roles\RolesMapper;

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new \SlimPostgres\App();

// config
$name = 'ed';
$username = 'eddie'; // must be unique or query will fail
$passwordClear = ''; // make it a good one https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html
$active = true; // bool
$roles = ['owner', 'bookkeeper']; // must be in roles table
// end config

/** getRoleIdsForRoles() throws exception if a role doesn't exist */
$administratorId = (AdministratorsMapper::getInstance())->create($name, $username, $passwordClear, (RolesMapper::getInstance())->getRoleIdsForRoles($roles), $active);

echo "administrator $administratorId inserted.\n";  
