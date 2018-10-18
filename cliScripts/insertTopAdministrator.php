<?php
declare(strict_types=1);

/** This script will insert an administrator with the TOP_ROLE as defined in config/constants.php, with all permissions assigned to the role */

/** begin config */
$name = '';
$username = ''; // must be unique and at least 4 characters or query will fail
$passwordClear = ''; // make it a good one https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html
/** end config */


use SlimPostgres\Entities\Administrators\Model\AdministratorsMapper;
use SlimPostgres\Entities\Roles\Model\RolesMapper;
use SlimPostgres\Entities\Permissions\Model\PermissionsMapper;

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new SlimPostgres\App();

pg_query("BEGIN");

$rolesMapper = RolesMapper::getInstance();

if (null === $topRoleId = $rolesMapper->getRoleIdForRole(TOP_ROLE)) {
    $topRoleId = (int) $rolesMapper->insert(['role' => TOP_ROLE]);
}
$administratorActive = true;

$administratorId = (AdministratorsMapper::getInstance())->create($name, $username, $passwordClear, [$topRoleId], $administratorActive);

/** assign all permissions to role */
$permissionsMapper = PermissionsMapper::getInstance();

foreach ($permissionsMapper->getObjects() as $permission) {
    if (!$permission->hasRole($topRoleId)) {
        $permissionsMapper->doInsertPermissionRole($permission->getId(), $topRoleId);
    }
}

pg_query("COMMIT");

echo "administrator $administratorId inserted.\n";  
