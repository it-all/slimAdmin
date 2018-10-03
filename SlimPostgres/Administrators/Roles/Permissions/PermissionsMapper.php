<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\Administrators\Roles\Permissions\Permission;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Database\DataMappers\MultiTableMapper;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\Postgres;

// Singleton
final class PermissionsMapper extends MultiTableMapper
{
    const TABLE_NAME = 'permissions';
    const ROLES_JOIN_TABLE_NAME = 'roles_permissions';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'permission' => self::TABLE_NAME . '.permission',
        'description' => self::TABLE_NAME . '.description',
        'rolesId' => self::ROLES_JOIN_TABLE_NAME . '.role_id',
        'active' => self::TABLE_NAME . '.active',
        'created' => self::TABLE_NAME . '.created',
    ];

    const ORDER_BY_COLUMN_NAME = 'created';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new PermissionsMapper();
        }
        return $instance;
    }

    private function __construct()
    {
        parent::__construct(new TableMapper(self::TABLE_NAME, '*', self::ORDER_BY_COLUMN_NAME), self::SELECT_COLUMNS, self::ORDER_BY_COLUMN_NAME);
    }

    /** any validation should be done prior */
    public function create(string $permission, ?string $description, array $roleIds, bool $active): int
    {
        // insert administrator then administrator_roles in a transaction
        pg_query("BEGIN");

        try {
            $permissionId = $this->insert($permission, $description, $active);
        } catch (\Exception $e) {
            $q = new QueryBuilder("ROLLBACK");
            $q->execute();
            throw $e;
        }

        try {
            $this->insertPermissionRoles((int) $permissionId, $roleIds);
        } catch (\Exception $e) {
            $q = new QueryBuilder("ROLLBACK");
            $q->execute();
            throw $e;
        }

        pg_query("COMMIT");
        return $permissionId;
    }

    private function insertPermissionRole(int $permissionId, int $roleId)
    {
        $q = new QueryBuilder("INSERT INTO ".self::ROLES_JOIN_TABLE_NAME." (permission_id, role_id) VALUES($1, $2)", $permissionId, $roleId);
        return $q->executeWithReturnField('id');
    }

    private function insertPermissionRoles(int $permissionId, array $roleIds): array
    {
        $permissionRoleIds = [];
        foreach ($roleIds as $roleId) {
            $permissionRoleIds[] = $this->insertPermissionRole($permissionId, (int) $roleId);
        }
        return $permissionRoleIds;
    }

    private function insert(string $permission, ?string $description = null, bool $active = true): int
    {
        if (strlen($description) == 0) {
            $description = null;
        }
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (permission, description, active) VALUES($1, $2, $3)", $permission, $description, Postgres::convertBoolToPostgresBool($active));
        return (int) $q->executeWithReturnField('id');
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

    /** returns array of results instead of recordset */
    private function selectArray(?string $selectColumns = null, array $whereColumnsInfo = null, string $orderBy = null): array
    {
        if ($selectColumns == null) {
            $selectColumns = $this->getSelectColumnsString();
        }

        $permissionsArray = []; // populate with 1 entry per permission with an array of role objects

        $pgResults = $this->select($selectColumns, $whereColumnsInfo, $orderBy);
        if (pg_num_rows($pgResults) > 0) {
            $rolesMapper = RolesMapper::getInstance();
            while ($record = pg_fetch_assoc($pgResults)) {
                // either add new permission or just new role based on whether permission already exists
                if (null === $key = $this->getPermissionsArrayKeyForId($permissionsArray, (int) $record['id'])) {
                    $permissionsArray[] = [
                        'id' => (int) $record['id'],
                        'permission' => $record['permission'],
                        'description' => $record['description'],
                        'roles' => [$rolesMapper->getObjectById((int) $record['role_id'])],
                        'active' => Postgres::convertPostgresBoolToBool($record['active']),
                        'created' => new \DateTimeImmutable($record['created']),
                    ];
                } else {
                    array_push($permissionsArray[$key]['roles'], $rolesMapper->getObjectById((int) $record['role_id']));
                }
            }
        }

        return $permissionsArray;
    }

    public function select(?string $columns = null, ?array $whereColumnsInfo = null, ?string $orderBy = null)
    {
        if ($whereColumnsInfo != null) {
            $this->validateWhere($whereColumnsInfo);
        }
        
        $selectColumnsString = ($columns === null) ? $this->getSelectColumnsString() : $columns;
        $selectClause = "SELECT " . $selectColumnsString;
        $orderBy = ($orderBy == null) ? $this->getOrderBy() : $orderBy;
        
        $q = new SelectBuilder($selectClause, $this->getFromClause(), $whereColumnsInfo, $orderBy);
        return $q->execute();
    }

    /** permissions joined with role_permissions. note that every permission must have at least 1 role assigned */
    private function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." JOIN ".self::ROLES_JOIN_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ROLES_JOIN_TABLE_NAME.".permission_id";
    }

    private function getObject(array $whereColumnsInfo): ?Permission
    {
        $q = new SelectBuilder($this->getSelectClause(), $this->getFromClause(), $whereColumnsInfo, $this->getOrderBy());
        $pgResults = $q->execute();
        if (pg_numrows($pgResults) > 0) {
            // there will be 1 record for each role
            $roles = [];
            $rolesMapper = RolesMapper::getInstance();
            while ($row = pg_fetch_assoc($pgResults)) {
                $roles[] = $rolesMapper->getObjectById((int) $row['role_id']);
                $lastRow = $row;
            }
            return $this->buildPermission((int) $lastRow['id'], $lastRow['permission'], $lastRow['description'], Postgres::convertPostgresBoolToBool($lastRow['active']), new \DateTimeImmutable($lastRow['created']), $roles);
        } else {
            return null;
        }
    }

    /** note roles array is validated in Permission constructor */
    public function buildPermission(int $id, string $permission, ?string $description, bool $active, \DateTimeImmutable $created, array $roles): Permission 
    {
        return new Permission($id, $permission, $description, $active, $created, $roles);
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

    public function isUpdatable(): bool
    {
        return true;
    }

    public function isDeletable(int $id): bool 
    {
        return true;
    }

    /** selects and converts recordset to array of objects and return */
    public function getObjects(array $whereColumnsInfo = null, string $orderBy = null): array 
    {
        $permissions = [];
        foreach ($this->selectArray(null, $whereColumnsInfo, $orderBy) as $permissionArray) {
            $permissions[] = $this->buildPermission($permissionArray['id'], $permissionArray['permission'], $permissionArray['description'], $permissionArray['active'], $permissionArray['created'], $permissionArray['roles']);
        }

        return $permissions;
    }
}
