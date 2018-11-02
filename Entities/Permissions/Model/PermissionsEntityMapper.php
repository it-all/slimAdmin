<?php
declare(strict_types=1);

namespace Entities\Permissions\Model;

use Infrastructure\Database\DataMappers\EntityMapper;
use Entities\Permissions\Model\Permission;
use Entities\Roles\Model\RolesTableMapper;
use Entities\Roles\Model\Role;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Database\Postgres;
use Exceptions;

// Singleton
final class PermissionsEntityMapper extends EntityMapper
{
    private $permissionsTableMapper;

    const TABLE_NAME = 'permissions';
    const ROLES_TABLE_NAME = 'roles';
    const ROLES_JOIN_TABLE_NAME = 'roles_permissions';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'title' => self::TABLE_NAME . '.title',
        'description' => self::TABLE_NAME . '.description',
        'roleId' => self::ROLES_JOIN_TABLE_NAME . '.role_id',
        'roles' => self::ROLES_TABLE_NAME . '.role',
        'active' => self::TABLE_NAME . '.active',
        'created' => self::TABLE_NAME . '.created',
    ];

    const ORDER_BY_COLUMN_NAME = 'title';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new PermissionsEntityMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        $this->permissionsTableMapper = PermissionsTableMapper::getInstance();
        parent::setDefaultSelectColumnsString();
    }

    public function getListViewTitle(): string 
    {
        return $this->permissionsTableMapper->getFormalTableName();
    }

    public function getInsertTitle(): string
    {
        return "Insert Permission";
    }

    public function getUpdateTitle(): string
    {
        return "Update Permission";
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->permissionsTableMapper->getUpdateColumnName();
    }

    public function getListViewSortColumn(): ?string 
    {
        return $this->permissionsTableMapper->getOrderByColumnName();
    }

    public function getListViewSortAscending(): bool 
    {
        return $this->permissionsTableMapper->getOrderByAsc();
    }

    /** any validation should be done prior */
    public function create(string $title, ?string $description, ?array $roleIds, bool $active): int
    {
        // add top role if not already there, as it is assigned to all permissions
        if ($roleIds === null || !in_array(TOP_ROLE, $roleIds)) {
            $roleIds[] = (RolesTableMapper::getInstance())->getTopRoleId();
        }

        // insert administrator then administrator_roles in a transaction
        pg_query("BEGIN");

        try {
            $permissionId = $this->permissionsTableMapper->callInsert($title, $description, $active);
        } catch (Exceptions\QueryFailureException $e) {
            pg_query("ROLLBACK");
            throw $e;
        }

        try {
            $this->insertPermissionRoles((int) $permissionId, $roleIds);
        } catch (Exceptions\QueryFailureException $e) {
            pg_query("ROLLBACK");
            throw $e;
        }

        pg_query("COMMIT");
        return $permissionId;
    }

    private function insertPermissionRoles(int $permissionId, array $roleIds): array
    {
        $permissionRoleIds = [];
        foreach ($roleIds as $roleId) {
            $permissionRoleIds[] = $this->doInsertPermissionRole($permissionId, (int) $roleId);
        }
        return $permissionRoleIds;
    }

    public function doInsertPermissionRole(int $permissionId, int $roleId)
    {
        $q = new QueryBuilder("INSERT INTO ".self::ROLES_JOIN_TABLE_NAME." (permission_id, role_id) VALUES($1, $2)", $permissionId, $roleId);
        return $q->executeWithReturnField('id');
    }

    // returns key of results array for matching 'id' key, null if not found
    // note, careful when checking return value as 0 can be returned (evaluates to false)
    private function getPermissionsArrayKeyForId(array $permissionsArray, int $id): ?int 
    {
        foreach ($permissionsArray as $key => $permission) {
            if ($permission['id'] == $id) {
                return $key;
            }
        }

        return null;
    }

    /** returns array of records or null */
    public function select(?string $columns = "*", ?array $whereColumnsInfo = null, ?string $orderBy = null): ?array
    {
        if ($whereColumnsInfo != null) {
            $this->validateWhere($whereColumnsInfo);
        }
        
        /** simply adding to the where clause below with the roles field will yield incomplete results, as not all roles for an administrator will be selected, so the subquery fn is called */
        if (is_array($whereColumnsInfo) && array_key_exists('roles.role', $whereColumnsInfo)) {
            return $this->selectWithRoleSubquery($columns, $whereColumnsInfo, $orderBy);
        }
        
        $columns = $columns ?? $this->defaultSelectColumnsString;
        $orderBy = $orderBy ?? $this->getOrderBy();
        
        $q = new SelectBuilder("SELECT $columns", $this->getFromClause(), $whereColumnsInfo, $orderBy);
        $pgResult = $q->execute();
        if (!$results = pg_fetch_all($pgResult)) {
            $results = null;
        }
        pg_free_result($pgResult);
        return $results;
    }
    
    /** to filter the permissions with certain roles and return all the roles the permissions have */
    private function selectWithRoleSubquery(?string $columns = null, array $whereColumnsInfo = null, string $orderBy = null)
    {
        $columns = $columns ?? $this->defaultSelectColumnsString;

        /** start subquery */
        $q = new QueryBuilder("SELECT $columns ".$this->getFromClause()." WHERE permissions.id IN (SELECT permissions.id FROM permissions JOIN roles_permissions ON permissions.id=roles_permissions.permission_id JOIN roles ON roles_permissions.role_id=roles.id WHERE");

        /** build subquery */
        $opCount = 0;
        foreach ($whereColumnsInfo['roles.role']['operators'] as $op) {
            $sqlVarCount = $opCount + 1;
            if ($opCount > 0) {
                $q->add(" OR ");
            }
            $q->add(" roles.role $op $$sqlVarCount", $whereColumnsInfo['roles.role']['values'][$opCount]);
            ++$opCount;
        }
        $q->add(") ORDER BY " . $this->getOrderBy());

        return $q->execute();
    }

    /** returns array of results instead of recordset */
    private function selectArray(?string $selectColumns = null, array $whereColumnsInfo = null, string $orderBy = null): array
    {
        $columns = $selectColumns ?? $this->defaultSelectColumnsString;

        $permissionsArray = []; // populate with 1 entry per permission with an array of role objects

        if(null !== $records = $this->select($columns, $whereColumnsInfo, $orderBy)) {
            $rolesTableMapper = RolesTableMapper::getInstance();
            foreach ($records as $record) {
                // either add new permission or just new role based on whether permission already exists
                if (null === $key = $this->getPermissionsArrayKeyForId($permissionsArray, (int) $record['id'])) {
                    $permissionsArray[] = [
                        'id' => (int) $record['id'],
                        'title' => $record['title'],
                        'description' => $record['description'],
                        'roles' => [$rolesTableMapper->getObjectById((int) $record['role_id'])],
                        'active' => Postgres::convertPostgresBoolToBool($record['active']),
                        'created' => new \DateTimeImmutable($record['created']),
                    ];
                } else {
                    array_push($permissionsArray[$key]['roles'], $rolesTableMapper->getObjectById((int) $record['role_id']));
                }
            }
        }

        return $permissionsArray;
    }

    /** permissions joined with role_permissions. note that every permission must have at least 1 role assigned */
    protected function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." JOIN ".self::ROLES_JOIN_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ROLES_JOIN_TABLE_NAME.".permission_id JOIN ".self::ROLES_TABLE_NAME." ON ".self::ROLES_JOIN_TABLE_NAME.".role_id=".self::ROLES_TABLE_NAME.".id";
    }

    protected function getOrderBy(): string 
    {
        return SELF::TABLE_NAME . "." . SELF::ORDER_BY_COLUMN_NAME . ", " . SELF::ROLES_TABLE_NAME . ".id";
    }
    
    private function getObject(array $whereColumnsInfo): ?Permission
    {
        $q = new SelectBuilder("SELECT ".$this->defaultSelectColumnsString, $this->getFromClause(), $whereColumnsInfo, $this->getOrderBy());
        $pgResults = $q->execute();
        if (pg_numrows($pgResults) > 0) {
            // there will be 1 record for each role
            $roles = [];
            $rolesTableMapper = RolesTableMapper::getInstance();
            while ($row = pg_fetch_assoc($pgResults)) {
                $roles[] = $rolesTableMapper->getObjectById((int) $row['role_id']);
                $lastRow = $row;
            }
            return $this->buildPermission((int) $lastRow['id'], $lastRow['title'], $lastRow['description'], Postgres::convertPostgresBoolToBool($lastRow['active']), new \DateTimeImmutable($lastRow['created']), $roles);
        } else {
            return null;
        }
    }

    /** note roles array is validated in Permission constructor */
    public function buildPermission(int $id, string $title, ?string $description, bool $active, \DateTimeImmutable $created, array $roles): Permission 
    {
        return new Permission($id, $title, $description, $active, $created, $roles);
    }

    public function getObjectById(int $id): ?Permission 
    {
        $whereColumnsInfo = [
            'permissions.id' => [
                'operators' => ["="],
                'values' => [$id]
            ]
        ];
        return $this->getObject($whereColumnsInfo);
    }

    public function getObjectByTitle(string $title, ?bool $active = null): ?Permission 
    {
        $whereColumnsInfo = [
            'permissions.title' => [
                'operators' => ["="],
                'values' => [$title]
            ]
        ];
        if ($active !== null) {
            $whereColumnsInfo['permissions.active'] = [
                'operators' => ["="],
                'values' => [Postgres::convertBoolToPostgresBool($active)]
            ];
        }
        return $this->getObject($whereColumnsInfo);
    }

    /** selects and converts recordset to array of objects and return */
    public function getObjects(array $whereColumnsInfo = null, string $orderBy = null): array 
    {
        $permissions = [];
        foreach ($this->selectArray(null, $whereColumnsInfo, $orderBy) as $permissionArray) {
            $permissions[] = $this->buildPermission($permissionArray['id'], $permissionArray['title'], $permissionArray['description'], $permissionArray['active'], $permissionArray['created'], $permissionArray['roles']);
        }

        return $permissions;
    }

    
    public function doUpdate(int $permissionId, array $changedFields) 
    {
        $changedPermissionFields = $this->permissionsTableMapper->getChangedFields($changedFields);

        pg_query("BEGIN");
        if (count($changedPermissionFields) > 0) {
            try {
                $this->permissionsTableMapper->updateByPrimaryKey($changedPermissionFields, $permissionId, false);
            } catch (\Exception $e) {
                pg_query("ROLLBACK");
                throw $e;
            }
        }
        if (isset($changedFields['roles']['add'])) {
            foreach ($changedFields['roles']['add'] as $addRoleId) {
                try {
                    $this->doInsertPermissionRole($permissionId, (int) $addRoleId);
                } catch (\Exception $e) {
                    pg_query("ROLLBACK");
                    throw $e;
                }
            }
        }
        if (isset($changedFields['roles']['remove'])) {
            foreach ($changedFields['roles']['remove'] as $deleteRoleId) {
                /** never remove top role from a permission */
                $deleteRole = (RolesTableMapper::getInstance())->getObjectById($deleteRoleId);
                if (!$deleteRole->isTop()) {
                    try {
                        $roleDeleteResult = $this->doDeletePermissionRole($permissionId, (int) $deleteRoleId);
                    } catch (\Exception $e) {
                        pg_query("ROLLBACK");
                        throw $e;
                    }
                    if ($roleDeleteResult === null) {
                        pg_query("ROLLBACK");
                        throw new \Exception("Role not found for permission during delete attempt");
                    }
                }
            }
        }
        pg_query("COMMIT");
    }

    /** returns deleted title */
    public function delete(int $id): string
    {
        // make sure there is a permission for the primary key
        if (null === $permission = $this->getObjectById($id)) {
            throw new Exceptions\QueryResultsNotFoundException();
        }

        $this->doDeleteTransaction($id);

        $title = $permission->getTitle();
        unset($permission);

        return $title;
    }

    /** any necessary validation should be performed prior to calling */
    private function doDeleteTransaction(int $permissionId)
    {
        pg_query("BEGIN");
        // $this->doDeletePermissionRoles($permissionId);

        try {
            $this->doDeletePermissionRoles($permissionId);
        } catch (\Exception $e) {
            pg_query("ROLLBACK");
            throw $e;
        }

        try {
            $this->permissionsTableMapper->delete($permissionId);
        } catch (\Exception $e) {
            pg_query("ROLLBACK");
            throw $e;
        }
        // $this->permissionsTableMapper->delete($permissionId);
        pg_query("COMMIT");
    }

    /** deletes the record(s) in the join table */
    private function doDeletePermissionRoles(int $permissionId)
    {
        $q = new QueryBuilder("DELETE FROM ".self::ROLES_JOIN_TABLE_NAME." WHERE permission_id = $1", $permissionId);
        $q->execute();
    }

    /** deletes a record in the join table and returns the id */
    /** returns null if not found */
    private function doDeletePermissionRole(int $permissionId, int $roleId): ?int
    {
        $q = new QueryBuilder("DELETE FROM ".self::ROLES_JOIN_TABLE_NAME." WHERE permission_id = $1 AND role_id = $2", $permissionId, $roleId);
        try {
            $deletedId = $q->executeWithReturnField('id');
        } catch (QueryResultsNotFoundException $e) {
            return null;
        }
        return (int) $deletedId;
    }
}
