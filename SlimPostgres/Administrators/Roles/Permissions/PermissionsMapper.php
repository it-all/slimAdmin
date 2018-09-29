<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\Database\DataMappers\MultiTableMapper;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\Postgres;

// Singleton
final class PermissionsMapper extends MultiTableMapper
{
    /** array id => [permission] */
    private $permissions;

    const TABLE_NAME = 'permissions';
    const ROLES_TABLE_NAME = 'roles';
    const ROLES_JOIN_TABLE_NAME = 'roles_permissions';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'permission' => self::TABLE_NAME . '.permission',
        'description' => self::TABLE_NAME . '.description',
        'rolesId' => self::ROLES_TABLE_NAME . '.id AS role_id',
        'roles' => self::ROLES_TABLE_NAME . '.role',
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

    // private function __construct()
    // {
    //     parent::__construct(self::TABLE_NAME);
    //     $this->setPermissions();
    // }

    private function __construct()
    {
        parent::__construct(new TableMapper(self::TABLE_NAME, '*', self::ORDER_BY_COLUMN_NAME), self::SELECT_COLUMNS, self::ORDER_BY_COLUMN_NAME);
    }

    private function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." JOIN ".self::ROLES_JOIN_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ROLES_JOIN_TABLE_NAME.".permission_id JOIN ".self::ROLES_TABLE_NAME." ON ".self::ROLES_JOIN_TABLE_NAME.".role_id = ".self::ROLES_TABLE_NAME.".id";
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

    // receives query results for administrators joined to roles and loads and returns model object or null if no results
    // note this only works for single administrator results (ie select by id)
    private function getObjectForResults($results): ?Permission 
    {
        if (pg_numrows($results) > 0) {
            // there will be 1 record for each role
            $roles = [];
            while ($row = pg_fetch_assoc($results)) {
                // repopulate id, name, passwordHash on each loop. it's either that or do a rowcount and populate them once but this is simpler and probably faster.
                
                // $roles[$row['role_id']] = [
                //     App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME => $row['role'],
                //     App::SESSION_ADMINISTRATOR_KEY_ROLES_LEVEL => $row['role_level']
                // ];
            }

            return new Permission((int) $row['id'], $row['permission'], $row['description'], Postgres::convertPostgresBoolToBool($row['active']), new \DateTimeImmutable($row['created']));

        } else {
            return null;
        }
    }

    private function getObject(array $whereColumnsInfo): ?Permission
    {
        $q = new SelectBuilder($this->getSelectClause(), $this->getFromClause(), $whereColumnsInfo, $this->getOrderBy());
        return $this->getObjectForResults($q->execute());
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

    private function buildPermissionFromRecord(array $record): Permission 
    {
        foreach ($this->primaryTableMapper->getColumns() as $column) {
            $columnName = $column->getName();
            if (!array_key_exists($columnName, $record)) {
                throw new \InvalidArgumentException("$columnName must exist in record");
            }
        }
        return new Permission((int) $record['id'], $record['permission'], $record['description'], Postgres::convertPostgresBoolToBool($record['active']), new \DateTimeImmutable($record['created']));
    }

    public function getPermissions(): array
    {
        return $this->permissions;
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
    public function getObjects(array $whereColumnsInfo = null): array 
    {
        $permissions = [];

        if ($pgResults = $this->select("*", $whereColumnsInfo)) {
            if (pg_num_rows($pgResults) > 0) {
                while ($record = pg_fetch_assoc($pgResults)) {
                    $permissions[] = $this->buildPermissionFromRecord($record);
                }
            }
        }
        return $permissions;
    }

    private function insert(string $permission, ?string $description, bool $active): int
    {
        if (strlen($description) == 0) {
            $description = null;
        }
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (permission, description, active) VALUES($1, $2, $3)", $permission, $description, Postgres::convertBoolToPostgresBool($active));
        return (int) $q->executeWithReturnField('id');
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
}
