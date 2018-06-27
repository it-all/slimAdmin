<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\App;
use SlimPostgres\Utilities\Functions;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\DataMappers\MultiTableMapper;

class AdministratorsMapper extends MultiTableMapper
{
    const TABLE_NAME = 'administrators';
    const ROLES_TABLE_NAME = 'roles';
    const ADM_ROLES_TABLE_NAME = 'administrator_roles';
    const ADMINISTRATORS_UPDATE_FIELDS = ['name', 'username', 'password'];

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'name' => self::TABLE_NAME . '.name',
        'username' => self::TABLE_NAME . '.username',
        'role' => self::ROLES_TABLE_NAME . '.role',
        'level' => self::ROLES_TABLE_NAME . '.level'
    ];

    public function __construct()
    {
        parent::__construct(new TableMapper(self::TABLE_NAME), self::SELECT_COLUMNS);
    }

    public function getOrderByColumnName(): ?string
    {
        return 'level';
    }

    // will be performing validation here. for now, assume validation has been performed
    public function create(string $name, string $username, string $password, array $roleIds)
    {
        // insert administrator then administrator_roles in a transaction
        $q = new QueryBuilder("BEGIN");
        $q->execute();

        if ($administratorId = $this->insert($name, $username, $password)) {
            if ($this->insertAdministratorRoles((int) $administratorId, $roleIds)) {
                $q = new QueryBuilder("COMMIT");
                $q->execute();
                return $administratorId;
            }
        }

        // a failure occurred
        $q = new QueryBuilder("ROLLBACK");
        $q->execute();
        return false;
    }

    private function insertAdministratorRoles(int $administratorId, array $roleIds): bool
    {
        foreach ($roleIds as $roleId) {
            if (!$administratorRoleId = $this->insertAdministratorRole($administratorId, (int) $roleId)) {
                return false;
            }
        }
        return true;
    }

    private function insertAdministratorRole(int $administratorId, int $roleId)
    {
        $returnField = 'id';
        $q = new QueryBuilder("INSERT INTO ".self::ADM_ROLES_TABLE_NAME." (administrator_id, role_id) VALUES($1, $2) RETURNING $returnField", $administratorId, $roleId);
        return $q->executeWithReturn($returnField);
    }

    // returns hashed password for insert/update 
    private function getHashedPassword(string $password): string 
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function insert(string $name, string $username, string $password)
    {
        $returnField = 'id';
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (name, username, password_hash) VALUES($1, $2, $3) RETURNING $returnField", $name, $username, $this->getHashedPassword($password));
        return $q->executeWithReturn($returnField);
    }

    // receives query results for administrators joined to roles and loads and returns model object
    private function getForResults($results): ?Administrator 
    {
        if (pg_numrows($results) > 0) {
            // there will be 1 record for each role
            $roles = [];
            while ($row = pg_fetch_assoc($results)) {
                // repopulate id, name, passwordHash on each loop. it's either that or do a rowcount and populate them once but this is simpler and probably faster.
                $id = $row['id'];
                $name = $row['name'];
                $username = $row['username'];
                $passwordHash = $row['password_hash'];
                $roles[$row['role_id']] = [
                    App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME => $row['role'],
                    App::SESSION_ADMINISTRATOR_KEY_ROLES_LEVEL => $row['role_level']
                ];
            }

            return new Administrator((int) $id, $name, $username, $passwordHash, $roles);

        } else {
            return null;
        }
    }

    private function getSelectClause(): string 
    {
        return "SELECT administrators.*, roles.id AS role_id, roles.level AS role_level, roles.role";
    }

    private function getFromClause(): string 
    {
        return "FROM administrators JOIN administrator_roles ON administrators.id = administrator_roles.administrator_id JOIN roles ON administrator_roles.role_id = roles.id";
    }

    public function getObjectById(int $id): ?Administrator 
    {
        $whereColumnsInfo = [
            'administrators.id' => [
                'operators' => ["="],
                'values' => [$id]
            ]
        ];

        $q = new SelectBuilder($this->getSelectClause(), $this->getFromClause(), $whereColumnsInfo);

        return $this->getForResults($q->execute());
    }

    public function getObjectByUsername(string $username): ?Administrator
    {
        $whereColumnsInfo = [
            'administrators.username' => [
                'operators' => ["="],
                'values' => [$username]
            ]
        ];

        $q = new SelectBuilder($this->getSelectClause(), $this->getFromClause(), $whereColumnsInfo);

        return $this->getForResults($q->execute());
    }

    public function select(string $columns = "*", array $whereColumnsInfo = null)
    {
        $selectClause = "SELECT $columns";
        $fromClause = "FROM ".self::TABLE_NAME." JOIN ".self::ADM_ROLES_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ADM_ROLES_TABLE_NAME.".administrator_id JOIN ".self::ROLES_TABLE_NAME." ON ".self::ADM_ROLES_TABLE_NAME.".role_id = ".self::ROLES_TABLE_NAME.".id";
        $orderByClause = "ORDER BY ".self::TABLE_NAME.".id, ".self::ROLES_TABLE_NAME.".level";
        if ($whereColumnsInfo != null) {
            $this->validateFilterColumns($whereColumnsInfo);
        }
        $q = new SelectBuilder($selectClause, $fromClause, $whereColumnsInfo, $orderByClause);
        return $q->execute();
    }

    private function addAdministratorToArray(array &$results, int $id, string $name, string $username, array $roles)
    {
        $results[] = [
            'id' => $id,
            'name' => $name,
            'username' => $username,
            'roles' => $roles
        ];
    }

    // returns array of results instead of recordset
    public function selectArray(string $columns = "*", array $whereColumnsInfo = null): array
    {
        $results = []; // populate with 1 entry per administrator with an array of roles
        if ($pgResults = $this->select($columns, $whereColumnsInfo)) {
            $lastId = 0;
            $roles = [];
            while ($record = pg_fetch_assoc($pgResults)) {
                $id = $record['id'];

                if ($id != $lastId) {
                    if ($lastId > 0) {
                        // enter last administrator looped through
                        $this->addAdministratorToArray($results, (int) $lastId, $name, $username, $roles);
                        // reset roles
                        $roles = [];
                    }

                    $name = $record['name'];
                    $username = $record['username'];
                    $roles[] = $record['role'];

                    $lastId = $id;

                } else {
                    // continuation of same administrator with new role
                    $roles[] = $record['role'];
                }
            }
            // add last administrator
            $this->addAdministratorToArray($results, (int) $lastId, $name, $username, $roles);
        }
        return $results;
    }

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

    private function deleteAdministrator(int $administratorId): ?string
    {
        $q = new QueryBuilder("DELETE FROM ".self::TABLE_NAME." WHERE id = $1 RETURNING username", $administratorId);
        if ($username = $q->executeWithReturn('username')) {
            return $username;
        }

        return null;
    }

    // any necessary validation should be performed prior to calling
    public function delete(int $administratorId)
    {
        $q = new QueryBuilder("BEGIN");
        $q->execute();
        $this->deleteAdministratorRoles($administratorId);
        $this->deleteAdministrator($administratorId);
        $q = new QueryBuilder("END");
        $q->execute();
    }

    public function deleteAdministratorRoles(int $administratorId)
    {
        $q = new QueryBuilder("DELETE FROM ".self::ADM_ROLES_TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        $q->execute();
    }

    // todo find out if 1 was deleted, and if not - throw an exception or at least put in a system event
    public function deleteAdministratorRole(int $administratorId, int $roleId)
    {
        $q = new QueryBuilder("DELETE FROM administrator_roles WHERE administrator_id = $1 AND role_id = $2", $administratorId, $roleId);
        $q->execute();
    }

    private function getChangedAdministratorFields(array $changedFields): array 
    {
        $searchFields = self::ADMINISTRATORS_UPDATE_FIELDS;
        $changedAdministratorFields = [];

        foreach ($searchFields as $searchField) {
            if (array_key_exists($searchField, $changedFields)) {
                if ($searchField == 'password') {
                    $changedAdministratorFields['password_hash'] = $this->getHashedPassword($changedFields['password']);
                } else {
                    $changedAdministratorFields[$searchField] = $changedFields[$searchField];
                }
            }

        }
        return $changedAdministratorFields;
    }

    public function update(int $administratorId, array $changedFields) 
    {
        $changedAdministratorFields = $this->getChangedAdministratorFields($changedFields);

        $q = new QueryBuilder("BEGIN");
        $q->execute();

        if (count($changedAdministratorFields) > 0) {
            $this->getPrimaryTableMapper()->updateByPrimaryKey($changedAdministratorFields, $administratorId, false);
        }
        if (isset($changedFields['roles']['add'])) {
            foreach ($changedFields['roles']['add'] as $addRoleId) {
                $this->insertAdministratorRole($administratorId, (int) $addRoleId);
            }
        }
        if (isset($changedFields['roles']['remove'])) {
            foreach ($changedFields['roles']['remove'] as $deleteRoleId) {
                $this->deleteAdministratorRole($administratorId, (int) $deleteRoleId);
            }
        }
        $q = new QueryBuilder("END");
        $q->execute();
    }
}
