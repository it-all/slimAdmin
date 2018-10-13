<?php
declare(strict_types=1);

/** CAUTION, this deletes the administrator, associated administrator_roles, login_attempts, and system events. Deleting login_attempts and system_events is like deleting log entries. It is not allowed from the AdministratorsMapper. This script should only be used for test administrators. */

define('APPLICATION_ROOT_DIRECTORY', realpath(__DIR__.'/..'));
require APPLICATION_ROOT_DIRECTORY . '/vendor/autoload.php';
require APPLICATION_ROOT_DIRECTORY . '/config/constants.php';

new \SlimPostgres\App();

// config
$username = ''; // must exist or exception will occur
// end config

$administratorsMapper =  \SlimPostgres\Administrators\AdministratorsMapper::getInstance();
if (null === $administratorId = $administratorsMapper->getAdministratorIdByUsername($username)) {
    throw new \Exception("Administrator not found for username $username");
}

pg_query("BEGIN");
deleteAdministratorRoles($administratorId);
deleteLoginAttempts($administratorId);
deleteSystemEvents($administratorId);
deleteAdministrator($administratorId);
pg_query("COMMIT");

function deleteAdministratorRoles(int $administratorId)
{
    $q = new \SlimPostgres\Database\Queries\QueryBuilder("DELETE FROM administrator_roles WHERE administrator_id = $administratorId");

    try {
        $q->execute();
        echo "administrator roles deleted.\n";  
    } catch (\Exception $e) {
        echo $e->getMessage() . "\n\n";
    }
}

function deleteLoginAttempts(int $administratorId)
{
    $q = new \SlimPostgres\Database\Queries\QueryBuilder("DELETE FROM login_attempts WHERE administrator_id = $administratorId");

    try {
        $q->execute();
        echo "administrator login_attempts deleted.\n";  
    } catch (\Exception $e) {
        echo $e->getMessage() . "\n\n";
    }
}

function deleteSystemEvents(int $administratorId)
{
    $q = new \SlimPostgres\Database\Queries\QueryBuilder("DELETE FROM system_events WHERE administrator_id = $administratorId");

    try {
        $q->execute();
        echo "administrator system_events deleted.\n";  
    } catch (\Exception $e) {
        echo $e->getMessage() . "\n\n";
    }
}

function deleteAdministrator(int $administratorId)
{
    $q = new \SlimPostgres\Database\Queries\QueryBuilder("DELETE FROM administrators WHERE id = $administratorId");

    try {
        $q->execute();
        echo "administrator $administratorId deleted.\n";  
    } catch (\Exception $e) {
        echo $e->getMessage() . "\n\n";
    }
}


