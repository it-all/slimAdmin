<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\MultiTable\MultiTableModel;

class AdministratorsModel extends MultiTableModel
{
    const TABLE_NAME = 'administrators';
    const ROLES_TABLE_NAME = 'roles';
    const ADM_ROLES_TABLE_NAME = 'administrator_roles';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'name' => self::TABLE_NAME . '.name',
        'username' => self::TABLE_NAME . '.username',
        'role' => 'roles.role',
        'level' => 'roles.level'
    ];

    public function __construct()
    {
        parent::__construct(new SingleTableModel(self::TABLE_NAME), self::SELECT_COLUMNS);
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

    private function insert(string $name, string $username, string $password)
    {
        $returnField = 'id';
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (name, username, password_hash) VALUES($1, $2, $3) RETURNING $returnField", $name, $username, password_hash($password, PASSWORD_DEFAULT));
        return $q->executeWithReturn($returnField);
    }

    public function getChangedColumnsValues(array $inputValues, array $record): array
    {
        $changedColumns = [];
        foreach ($inputValues as $columnName => $value) {
            // throw out any new values that are not table columns
            if ($this->getColumnByName($columnName) !== null && $value != $record[$columnName]) {
                // do not add blank password to changed columns
                if ($columnName == 'password') {
                    if (mb_strlen($value) > 0) {
                        $passwordHash = password_hash($value, PASSWORD_DEFAULT);
                        if ($passwordHash != $record['password_hash']) {
                            $changedColumns['password_hash'] = $passwordHash;
                        }
                    }
                } else {
                    $changedColumns[$columnName] = $value;
                }
            }
        }
        return $changedColumns;
    }

    public function checkRecordExistsForUsername(string $username): bool
    {
        $q = new QueryBuilder("SELECT id FROM ".self::TABLE_NAME." WHERE username = $1", $username);
        $res = $q->execute();
        return pg_num_rows($res) > 0;
    }

    public function getByUsername(string $username): ?Administrator
    {
        $q = new QueryBuilder("SELECT ".self::TABLE_NAME.".*, r.role FROM ".self::TABLE_NAME." JOIN administrator_roles admr ON ".self::TABLE_NAME.".id = admr.administrator_id JOIN roles r ON admr.role_id = r.id WHERE ".self::TABLE_NAME.".username = $1", $username);
        $results = $q->execute();
        if (pg_numrows($results) > 0) {
            // there will be 1 record for each role
            $roles = [];
            while ($row = pg_fetch_assoc($results)) {
                // repopulate id, name, passwordHash on each loop. it's either that or do a rowcount and populate them once but this is simpler and probably faster.
                $id = $row['id'];
                $name = $row['name'];
                $passwordHash = $row['password_hash'];
                $roles[] = $row['role'];
            }
            return new Administrator((int) $id, $name, $username, $passwordHash, $roles);
        } else {
            return null;
        }
    }

    public function selectForUsername(string $username)
    {
        return $this->select(['username' => $username]);
    }

    public function select(array $whereColumnsInfo = null)
    {
        $selectClause = "SELECT ";
        $columnCount = 1;
        foreach (self::SELECT_COLUMNS as $columnNameSql) {
            $selectClause .= $columnNameSql;
            if ($columnCount != count(self::SELECT_COLUMNS)) {
                $selectClause .= ",";
            }
            $columnCount++;
        }
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
    public function selectArray(array $whereColumnsInfo = null): array
    {
        $results = []; // populate with 1 entry per administrator with an array of roles
        if ($pgResults = $this->select($whereColumnsInfo)) {
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

    public function selectArrayWithRolesString(array $whereColumnsInfo = null): array
    {
        $administrators = [];
        $results = $this->selectArray($whereColumnsInfo);
        foreach ($results as $index => $administrator) {
            $administrators[$index] = $administrator;
            $administrators[$index]['roles'] = implode(", ", $administrators[$index]['roles']);
        }
        return $administrators;
    }
}
