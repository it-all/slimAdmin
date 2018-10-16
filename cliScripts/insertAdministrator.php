<?php
declare(strict_types=1);

use \SlimPostgres\Entities\Administrators\Model\AdministratorsMapper;
use \SlimPostgres\Entities\Roles\Model\RolesMapper;

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new \SlimPostgres\App();

// config
$name = '';
$username = ''; // must be unique and at least 4 characters or query will fail
$passwordClear = ''; // make it a good one https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html
$active = true; // bool
$roles = ['owner']; // if not found in roles table, new role will be inserted. for initial installation, this should probably match the TOP_ROLE defined in config/constants.php
// end config

pg_query("BEGIN");

$roleIds = []; /** populate to construct administrator */
$rolesMapper = RolesMapper::getInstance();
foreach ($roles as $role) {
    if (null === $roleId = $rolesMapper->getRoleIdForRole($role)) {
        $roleId = $rolesMapper->insert(['role' => $role]);
    }
    $roleIds[] = $roleId;
}

$administratorId = (AdministratorsMapper::getInstance())->create($name, $username, $passwordClear, $roleIds, $active);

pg_query("COMMIT");

echo "administrator $administratorId inserted.\n";  
