<?php
declare(strict_types=1);

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new \SlimPostgres\App();

// config
$name = 'Fred';
$username = 'freddie';
$passwordClear = ''; // make it a good one https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html
$active = 't'; // t or f
$roles = ['owner', 'bookkeeper']; // must be in roles table
// end config

$administratorsMapper =  \SlimPostgres\Administrators\AdministratorsMapper::getInstance();

/** convert roles to id's to verify they exist */
$roleIds = getRoleIdsForRoles($roles);

$passwordHash = $administratorsMapper->getHashedPassword($passwordClear);

$q = new \SlimPostgres\Database\Queries\QueryBuilder("INSERT INTO administrators (name, username, password_hash, active) VALUES('$name', '$username', '$passwordHash', '$active')");

try {
    $administratorId = $q->executeWithReturnField("id");
    echo "administrator $administratorId inserted.\n";  
} catch (\Exception $e) {
    echo $e->getMessage() . "\n\n";
}

foreach ($roleIds as $roleId) {
    $q = new \SlimPostgres\Database\Queries\QueryBuilder("INSERT INTO administrator_roles (administrator_id, role_id) VALUES($administratorId, $roleId)");
    try {
        $roleId = $q->executeWithReturnField("id");
        echo "role $roleId inserted.\n";  
    } catch (\Exception $e) {
        echo $e->getMessage() . "\n\n";
    }
}

function getRoleIdsForRoles(array $roles): array 
{
    if (count($roles) == 0) {
        throw new \Exception("Roles array must be populated.");
    }

    $rolesMapper =  \SlimPostgres\Administrators\Roles\RolesMapper::getInstance();

    $roleIds = [];
    foreach ($roles as $role) {
        /** note exception will be thrown if doesn't exist */
        $roleIds[] = $rolesMapper->getRoleIdForRole($role);
    }

    return $roleIds;
}
