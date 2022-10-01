<?php
declare(strict_types=1);

namespace Entities\Roles\Model;

use Infrastructure\Database\Queries\QueryBuilder;

// Singleton
/** Not extending EntityMapper as no need for abstract functions */
final class RolesEntityMapper
{
    const TABLE_NAME = 'roles';
    const ADMINISTRATORS_JOIN_TABLE_NAME = 'administrator_roles';
    const PERMISSIONS_JOIN_TABLE_NAME = 'roles_permissions';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new RolesEntityMapper();
        }
        return $instance;
    }

    public function hasAdministrator(int $roleId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM ".self::ADMINISTRATORS_JOIN_TABLE_NAME." WHERE role_id = $1", $roleId);
        if (null === $row = $q->getRow()) {
            return false;
        }
        return (bool) $row[0];
    }

    public function hasPermissionAssigned(int $roleId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM ".self::PERMISSIONS_JOIN_TABLE_NAME." WHERE role_id = $1", $roleId);
        if (null === $row = $q->getRow()) {
            return false;
        }
        return (bool) $row[0];
    }

    public function deleteForAdministrator(int $administratorId) 
    {
        $q = new QueryBuilder("DELETE FROM ".self::ADMINISTRATORS_JOIN_TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        $q->execute();
    }
}
