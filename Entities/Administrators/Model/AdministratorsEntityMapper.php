<?php
declare(strict_types=1);

namespace Entities\Administrators\Model;

use Infrastructure\Database\DataMappers\EntityMapper;
use Exceptions;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Database\Postgres;
use Infrastructure\Security\Authorization\AuthorizationService;
use Infrastructure\Security\Authentication\AuthenticationService;
use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\Database\DataMappers\ListViewMappers;

final class AdministratorsEntityMapper extends EntityMapper implements ListViewMappers
{
    private $administratorsTableMapper;

    const TABLE_NAME = 'administrators';
    const ROLES_TABLE_NAME = 'roles';
    const ADM_ROLES_TABLE_NAME = 'administrator_roles';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'name' => self::TABLE_NAME . '.name',
        'username' => self::TABLE_NAME . '.username',
        'passwordHash' => self::TABLE_NAME . '.password_hash',
        'roleId' => self::ROLES_TABLE_NAME . '.id AS role_id',
        'roles' => self::ROLES_TABLE_NAME . '.role',
        'active' => self::TABLE_NAME . '.active',
        'created' => self::TABLE_NAME . '.created',
    ];

    const ORDER_BY_COLUMN_NAME = 'name';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new AdministratorsEntityMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        parent::__construct();
        $this->administratorsTableMapper = AdministratorsTableMapper::getInstance();
        parent::setDefaultSelectColumnsString();
    }

    public function getListViewTitle(): string 
    {
        return $this->administratorsTableMapper->getFormalTableName();
    }

    public function getInsertTitle(): string
    {
        return "Insert Administrator";
    }

    public function getUpdateTitle(): string
    {
        return "Update Administrator";
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->administratorsTableMapper->getUpdateColumnName();
    }

    public function getListViewSortColumn(): ?string 
    {
        return $this->administratorsTableMapper->getOrderByColumnName();
    }

    public function getListViewSortAscending(): bool 
    {
        return $this->administratorsTableMapper->getOrderByAsc();
    }

    /** any validation should be done prior */
    public function create(string $name, string $username, string $passwordClear, array $roleIds, bool $active): int
    {
        // insert administrator then administrator_roles in a transaction
        pg_query($this->pgConnection, "BEGIN");

        try {
            $administratorId = $this->administratorsTableMapper->callInsert($name, $username, $passwordClear, $active);
        } catch (\Exception $e) {
            pg_query($this->pgConnection, "ROLLBACK");
            throw $e;
        }

        try {
            $this->insertAdministratorRoles((int) $administratorId, $roleIds);
        } catch (\Exception $e) {
            pg_query($this->pgConnection, "ROLLBACK");
            throw $e;
        }

        pg_query($this->pgConnection, "COMMIT");
        return $administratorId;
    }

    public function insertAdministratorRoles(int $administratorId, array $roleIds): array
    {
        $administratorRoleIds = [];
        foreach ($roleIds as $roleId) {
            $administratorRoleIds[] = $this->doInsertAdministratorRole($administratorId, (int) $roleId);
        }
        return $administratorRoleIds;
    }

    private function doInsertAdministratorRole(int $administratorId, int $roleId)
    {
        $q = new QueryBuilder("INSERT INTO ".self::ADM_ROLES_TABLE_NAME." (administrator_id, role_id) VALUES($1, $2)", $administratorId, $roleId);
        return $q->executeWithReturnField('id');
    }

    protected function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." 
            JOIN ".self::ADM_ROLES_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ADM_ROLES_TABLE_NAME.".administrator_id 
            JOIN ".self::ROLES_TABLE_NAME." ON ".self::ADM_ROLES_TABLE_NAME.".role_id = ".self::ROLES_TABLE_NAME.".id";
    }

    protected function getOrderBy(): string 
    {
        return SELF::TABLE_NAME . "." . SELF::ORDER_BY_COLUMN_NAME . ", " . SELF::ADM_ROLES_TABLE_NAME . ".role_id";
    }

    private function getObject(array $whereColumnsInfo): ?Administrator
    {
        $q = new SelectBuilder("SELECT ".$this->defaultSelectColumnsString, $this->getFromClause(), $whereColumnsInfo, $this->getOrderBy());

        $pgResults = $q->execute();
        if (pg_num_rows($pgResults) > 0) {
            // there will be 1 record for each role
            $roles = [];
            $rolesTableMapper = RolesTableMapper::getInstance();
            while ($row = pg_fetch_assoc($pgResults)) {
                $roles[] = $rolesTableMapper->getObjectById((int) $row['role_id']);
                $lastRow = $row;
            }
            
            return $this->buildAdministrator((int) $lastRow['id'], $lastRow['name'], $lastRow['username'], $lastRow['password_hash'], Postgres::convertPostgresBoolToBool($lastRow['active']), new \DateTimeImmutable($lastRow['created']), $roles);
        } else {
            return null;
        }
    }

    public function buildAdministrator(int $id, string $name, string $username, string $passwordHash, bool $active, \DateTimeImmutable $created, array $roles, ?AuthenticationService $authentication = null, ?AuthorizationService $authorization = null) 
    {
        return new Administrator($id, $name, $username, $passwordHash, $roles, $active, $created, $authentication, $authorization);
    }

    public function getObjectById(int $id): ?Administrator 
    {
        $whereColumnsInfo = [
            'administrators.id' => [
                'operators' => ["="],
                'values' => [$id]
            ]
        ];
        return $this->getObject($whereColumnsInfo);
    }

    public function getObjectByUsername(string $username, bool $activeOnly = true): ?Administrator
    {
        $whereColumnsInfo = [
            'administrators.username' => [
                'operators' => ["="],
                'values' => [$username]
            ]
        ];
        if ($activeOnly) {
            $whereColumnsInfo['administrators.active'] = [
                'operators' => ["="],
                'values' => [Postgres::BOOLEAN_TRUE]
            ];
        }
        return $this->getObject($whereColumnsInfo);
    }

    /** return array of records or null */
    public function select(?string $columns = "*", ?array $whereColumnsInfo = null, ?string $orderBy = null, ?int $limit = null): ?array
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

        $q = new SelectBuilder("SELECT $columns", $this->getFromClause(), $whereColumnsInfo, $orderBy, $limit);
        return $q->executeGetArray();
    }

    /** to filter the administrators with certain roles and return all the roles the administrators have */
    private function selectWithRoleSubquery(?string $columns = null, array $whereColumnsInfo = null, string $orderBy = null)
    {
        $columns = $columns ?? $this->defaultSelectColumnsString;
        $orderBy = $orderBy ?? $this->getOrderBy();

        /** start subquery */
        $q = new QueryBuilder("SELECT $columns ".$this->getFromClause()." WHERE administrators.id IN (SELECT administrators.id FROM administrators JOIN administrator_roles ON administrators.id=administrator_roles.administrator_id JOIN roles ON administrator_roles.role_id=roles.id WHERE");

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
        $q->add(") ORDER BY " . $orderBy);

        return $q->executeGetArray();
    }

    /* returns key of results array for matching 'id' key, null if not found
     * note, careful when checking return value as 0 can be returned (evaluates to false)
     */
    private function getAdministratorsArrayKeyForId(array $administratorsArray, int $id): ?int 
    {
        foreach ($administratorsArray as $key => $administrator) {
            if ($administrator['id'] == $id) {
                return $key;
            }
        }

        return null;
    }

    /** returns array of results instead of recordset */
    private function selectArray(?string $selectColumns = null, array $whereColumnsInfo = null, string $orderBy = null): array
    {
        $columns = $selectColumns ?? $this->defaultSelectColumnsString;

        $administratorsArray = []; // populate with 1 entry per administrator with an array of roles
        
        if (null !== $records = $this->select($columns, $whereColumnsInfo, $orderBy)) {
            $rolesTableMapper = RolesTableMapper::getInstance();
            foreach ($records as $record) {
                // either add new administrator or just new role based on whether administrator already exists
                if (null === $key = $this->getAdministratorsArrayKeyForId($administratorsArray, (int) $record['id'])) {
                    $administratorsArray[] = [
                        'id' => (int) $record['id'],
                        'name' => $record['name'],
                        'username' => $record['username'],
                        'passwordHash' => $record['password_hash'],
                        'roles' => [$rolesTableMapper->getObjectById((int) $record['role_id'])],
                        'active' => Postgres::convertPostgresBoolToBool($record['active']),
                        'created' => new \DateTimeImmutable($record['created']),
                    ];
                } else {
                    array_push($administratorsArray[$key]['roles'], $rolesTableMapper->getObjectById((int) $record['role_id']));
                }
            }
        }

        return $administratorsArray;
    }

    public function getObjects(array $whereColumnsInfo = null, string $orderBy = null, AuthenticationService $authentication, AuthorizationService $authorization): array 
    {
        $administrators = [];
        foreach ($this->selectArray(null, $whereColumnsInfo, $orderBy) as $administratorArray) {
            $administrators[] = $this->buildAdministrator($administratorArray['id'], $administratorArray['name'], $administratorArray['username'], $administratorArray['passwordHash'], $administratorArray['active'], $administratorArray['created'], $administratorArray['roles'], $authentication, $authorization);
        }

        return $administrators;
    }

    // instead of having roles returned as an array, it will be returned as a string
    public function selectArrayWithRolesString(string $columns = "*", array $whereColumnsInfo = null): array
    {
        $administrators = [];
        $results = $this->selectArray($columns, $whereColumnsInfo);
        foreach ($results as $index => $administrator) {
            $administrators[$index] = $administrator;
            $administrators[$index]['roles'] = implode(", ", $administrators[$index]['roles']);
        }
        return $administrators;
    }

    /** does validation for delete then deletes and returns deleted username */
    public function delete(int $id, AuthenticationService $authentication, AuthorizationService $authorization): string
    {
        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->getObjectById($id)) {
            throw new Exceptions\QueryResultsNotFoundException();
        }

        $administrator->setAuth($authentication, $authorization);

        if (!$administrator->isDeletable()) {
            throw new Exceptions\UnallowedActionException($administrator->getNotDeletableReason());
        }

        $this->doDeleteTransaction($id);

        $username = $administrator->getUsername();
        unset($administrator);

        return $username;
    }

    // any necessary validation should be performed prior to calling
    private function doDeleteTransaction(int $administratorId)
    {
        pg_query($this->pgConnection, "BEGIN");
        try {
            $this->doDeleteAdministratorRoles($administratorId);
        } catch (\Exception $e) {
            pg_query($this->pgConnection, "ROLLBACK");
            throw $e;
        } 
        try {
            $this->administratorsTableMapper->delete($administratorId);
        } catch (\Exception $e) {
            pg_query($this->pgConnection, "ROLLBACK");
            throw $e;
        } 
        pg_query($this->pgConnection, "COMMIT");
    }

    /** deletes the record(s) in the join table */
    private function doDeleteAdministratorRoles(int $administratorId)
    {
        $q = new QueryBuilder("DELETE FROM ".self::ADM_ROLES_TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        $q->execute();
    }

    /** deletes a record in the join table and returns the id */
    /** returns null if not found */
    private function doDeleteAdministratorRole(int $administratorId, int $roleId): ?int
    {
        $q = new QueryBuilder("DELETE FROM ".self::ADM_ROLES_TABLE_NAME." WHERE administrator_id = $1 AND role_id = $2", $administratorId, $roleId);
        try {
            $deletedId = $q->executeWithReturnField('id');
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return null;
        }
        return (int) $deletedId;
    }
    
    public function doUpdate(int $administratorId, array $changedFields) 
    {
        $changedAdministratorFields = $this->administratorsTableMapper->getChangedFields($changedFields);

        pg_query($this->pgConnection, "BEGIN");
        if (count($changedAdministratorFields) > 0) {
            try {
                $this->administratorsTableMapper->updateByPrimaryKey($changedAdministratorFields, $administratorId, false);
            } catch (\Exception $e) {
                pg_query($this->pgConnection, "ROLLBACK");
                throw $e;
            }
        }
        if (isset($changedFields['roles']['add'])) {
            foreach ($changedFields['roles']['add'] as $addRoleId) {
                try {
                    $this->doInsertAdministratorRole($administratorId, (int) $addRoleId);
                } catch (\Exception $e) {
                    pg_query($this->pgConnection, "ROLLBACK");
                    throw $e;
                }
            }
        }
        if (isset($changedFields['roles']['remove'])) {
            foreach ($changedFields['roles']['remove'] as $deleteRoleId) {
                try {
                    $roleDeleteResult = $this->doDeleteAdministratorRole($administratorId, (int) $deleteRoleId);
                } catch (\Exception $e) {
                    pg_query($this->pgConnection, "ROLLBACK");
                    throw $e;
                }
                if ($roleDeleteResult === null) {
                    pg_query($this->pgConnection, "ROLLBACK");
                    throw new \Exception("Role not found for administrator during delete attempt");
                }
            }
        }
        pg_query($this->pgConnection, "COMMIT");
    }

    // returns roles.id for first administrator_roles for administrator
    public function getFirstAdministratorRoleName(int $administratorId): string 
    {
        $q = new QueryBuilder("SELECT roles.role FROM administrator_roles adro JOIN roles ON adro.role_id = roles.id WHERE adro.administrator_id = $1 ORDER BY adro.created LIMIT 1", $administratorId);
        return $q->getRow()[0];
    }
}
