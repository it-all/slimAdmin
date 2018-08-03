<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\ListViewMappers;
use SlimPostgres\App;
use SlimPostgres\Exceptions;
use SlimPostgres\Utilities\Functions;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\DataMappers\MultiTableMapper;
use SlimPostgres\Security\Authentication\AuthenticationService;
use SlimPostgres\Security\Authorization\AuthorizationService;
use SlimPostgres\SystemEvents\SystemEventsMapper;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsMapper;

// Singleton
final class AdministratorsMapper extends MultiTableMapper
{
    const TABLE_NAME = 'administrators';
    const ROLES_TABLE_NAME = 'roles';
    const ADM_ROLES_TABLE_NAME = 'administrator_roles';
    const ADMINISTRATORS_UPDATE_FIELDS = ['name', 'username', 'password'];

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'name' => self::TABLE_NAME . '.name',
        'username' => self::TABLE_NAME . '.username',
        'passwordHash' => self::TABLE_NAME . '.password_hash',
        'roles' => self::ROLES_TABLE_NAME . '.role',
        'level' => self::ROLES_TABLE_NAME . '.level'
    ];

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new AdministratorsMapper();
        }
        return $instance;
    }

    private function __construct()
    {
        parent::__construct(new TableMapper(self::TABLE_NAME, '*', 'name'), self::SELECT_COLUMNS);
    }

    public function getOrderByColumnName(): ?string
    {
        return 'name';
    }

    // will be performing validation here. for now, assume validation has been performed
    public function create(string $name, string $username, string $password, array $roleIds)
    {
        // insert administrator then administrator_roles in a transaction
        $q = new QueryBuilder("BEGIN");
        $q->execute();

        try {
            $administratorId = $this->insert($name, $username, $password);
        } catch (\Exception $e) {
            $q = new QueryBuilder("ROLLBACK");
            $q->execute();
            throw $e;
        }

        try {
            $this->insertAdministratorRoles((int) $administratorId, $roleIds);
        } catch (\Exception $e) {
            $q = new QueryBuilder("ROLLBACK");
            $q->execute();
            throw $e;
        }

        $q = new QueryBuilder("COMMIT");
        $q->execute();
        return $administratorId;
    }

    private function insertAdministratorRoles(int $administratorId, array $roleIds)
    {
        foreach ($roleIds as $roleId) {
            $this->insertAdministratorRole($administratorId, (int) $roleId);
        }
    }

    private function insertAdministratorRole(int $administratorId, int $roleId)
    {
        $q = new QueryBuilder("INSERT INTO ".self::ADM_ROLES_TABLE_NAME." (administrator_id, role_id) VALUES($1, $2)", $administratorId, $roleId);
        return $q->executeWithReturnField('id');
    }

    // returns hashed password for insert/update 
    private function getHashedPassword(string $password): string 
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function insert(string $name, string $username, string $password)
    {
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (name, username, password_hash) VALUES($1, $2, $3)", $name, $username, $this->getHashedPassword($password));
        return $q->executeWithReturnField('id');
    }

    // receives query results for administrators joined to roles and loads and returns model object
    // note this only works for single administrator results (ie select by id)
    private function getObjectForResults($results): ?Administrator 
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

    private function getOrderBy(): string 
    {
        return 'administrators.name';
    }

    private function getObject(array $whereColumnsInfo): ?Administrator
    {
        $q = new SelectBuilder($this->getSelectClause(), $this->getFromClause(), $whereColumnsInfo, $this->getOrderBy()); // order by level
        return $this->getObjectForResults($q->execute());
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

    public function getObjectByUsername(string $username): ?Administrator
    {
        $whereColumnsInfo = [
            'administrators.username' => [
                'operators' => ["="],
                'values' => [$username]
            ]
        ];
        return $this->getObject($whereColumnsInfo);
    }

    /** to filter the administrators with certain roles and return all the roles the administrators have */
    private function selectWithRoleSubquery(string $columns = "*", array $whereColumnsInfo = null, string $orderBy = null)
    {
        /** start subquery */
        $q = new QueryBuilder("SELECT $columns FROM administrators JOIN administrator_roles ON administrators.id=administrator_roles.administrator_id JOIN roles ON administrator_roles.role_id=roles.id WHERE administrators.id IN (SELECT administrators.id FROM administrators JOIN administrator_roles ON administrators.id=administrator_roles.administrator_id JOIN roles ON administrator_roles.role_id=roles.id WHERE");

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
        $q->add(")");

        return $q->execute();
    }

    public function select(string $columns = "*", array $whereColumnsInfo = null, string $orderBy = null)
    {
        if ($whereColumnsInfo != null) {
            $this->validateWhere($whereColumnsInfo);
        }
        
        /** simply adding to the where clause below with the roles field will yield incomplete results, as not all roles for an administrator will be selected, so the subquery fn is called */
        if (is_array($whereColumnsInfo) && array_key_exists('roles.role', $whereColumnsInfo)) {
            return $this->selectWithRoleSubquery($columns, $whereColumnsInfo, $orderBy);
        }
        
        $selectClause = "SELECT $columns";
        $fromClause = "FROM ".self::TABLE_NAME." JOIN ".self::ADM_ROLES_TABLE_NAME." ON ".self::TABLE_NAME.".id = ".self::ADM_ROLES_TABLE_NAME.".administrator_id JOIN ".self::ROLES_TABLE_NAME." ON ".self::ADM_ROLES_TABLE_NAME.".role_id = ".self::ROLES_TABLE_NAME.".id";
        $orderBy = ($orderBy == null) ? $this->getOrderBy() : $orderBy;
        
        $q = new SelectBuilder($selectClause, $fromClause, $whereColumnsInfo, $orderBy);
        return $q->execute();
    }

    private function addRecordToArray(array &$results, array $record) 
    {
        $newRecord = [
            'id' => (int) $record['id'],
            'name' => $record['name'],
            'username' => $record['username'],
            'passwordHash' => $record['password_hash'],
            'roles' => [$record['role']]
        ];

        $results[] = $newRecord;
    }

    // adds new role to administrator results array for results key
    private function addRoleToAdministratorRoles(array &$administratorsArray, int $key, string $role) 
    {
        array_push($administratorsArray[$key]['roles'], $role);
    }

    // returns key of results array for matching 'id' key, null if not found
    // note, careful when checking return value as 0 can be returned (evaluates to false)
    private function getAdministratorsArrayKeyForId(array $administratorsArray, int $id): ?int 
    {
        foreach ($administratorsArray as $key => $administrator) {
            if ($administrator['id'] == $id) {
                return $key;
            }
        }

        return null;
    }

    // returns array of results instead of recordset
    public function selectArray(?string $selectColumns = null, array $whereColumnsInfo = null, string $orderBy = null): array
    {
        if ($selectColumns == null) {
            $selectColumns = $this->getSelectColumnsString();
        }

        $administratorsArray = []; // populate with 1 entry per administrator with an array of roles
        if ($pgResults = $this->select($selectColumns, $whereColumnsInfo, $orderBy)) {
            if (pg_num_rows($pgResults) > 0) {
                while ($record = pg_fetch_assoc($pgResults)) {
                    // either add new administrator or just new role based on whether administrator already exists
                    if (null !== $key = $this->getAdministratorsArrayKeyForId($administratorsArray, (int) $record['id'])) {
                        $this->addRoleToAdministratorRoles($administratorsArray, $key, $record['role']);
                    } else {
                        $this->addRecordToArray($administratorsArray, $record);
                    }
                }
            }
        }

        return $administratorsArray;
    }

    public function getObjects(array $whereColumnsInfo = null, string $orderBy = null, AuthenticationService $authentication, AuthorizationService $authorization): array 
    {
        $administrators = [];
        foreach ($this->selectArray(null, $whereColumnsInfo, $orderBy) as $administratorArray) {
            $administrators[] = new Administrator($administratorArray['id'], $administratorArray['name'], $administratorArray['username'], $administratorArray['passwordHash'], $administratorArray['roles'], $authentication, $authorization);
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

    /** ensure that $administrator can be deleted based on business rules */
    public function validateDelete(Administrator $administrator) 
    {
        $id = $administrator->getId();

        // make sure the current administrator is not deleting her/himself
        if ($administrator->isLoggedIn()) {
            throw new Exceptions\UnallowedActionException("Administrator cannot delete own account: id $id");
        }

        // non-top dogs cannot delete top dogs
        if (!$administrator->getAuthorization()->hasTopRole() && $administrator->hasTopRole()) {
            throw new Exceptions\UnallowedActionException("Not authorized to delete administrator: id $id");
        }

        // make sure there are no system events for administrator being deleted
        if ((SystemEventsMapper::getInstance())->existForAdministrator($id)) {
            throw new Exceptions\UnallowedActionException("System events exist for administrator: id $id");
        }

        // make sure there are no login attempts for administrator being deleted
        $loginsMapper = LoginAttemptsMapper::getInstance();
        if ($loginsMapper->hasAdministrator($id)) {
            throw new Exceptions\UnallowedActionException("Login attempts exist for administrator: id $id");
        }
    }

    // does validation for delete then deletes and returns deleted username
    public function delete(int $id, AuthenticationService $authentication, AuthorizationService $authorization): string
    {
        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->getObjectById($id)) {
            throw new Exceptions\QueryResultsNotFoundException();
        }

        $administrator->setAuth($authentication, $authorization);

        /** will throw exception if not valid */
        $this->validateDelete($administrator);

        $this->doDelete($id);

        $username = $administrator->getUsername();
        unset($administrator);

        return $username;
    }

    // any necessary validation should be performed prior to calling
    private function doDelete(int $administratorId)
    {
        $q = new QueryBuilder("BEGIN");
        $q->execute();
        $this->deleteAdministratorRoles($administratorId);
        $this->deleteAdministrator($administratorId);
        $q = new QueryBuilder("END");
        $q->execute();
    }

    // deletes the record in the join table
    private function deleteAdministratorRoles(int $administratorId)
    {
        $q = new QueryBuilder("DELETE FROM ".self::ADM_ROLES_TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        $q->execute();
    }

    // todo find out if 1 was deleted, and if not - throw an exception or at least put in a system event
    private function deleteAdministratorRole(int $administratorId, int $roleId)
    {
        $q = new QueryBuilder("DELETE FROM administrator_roles WHERE administrator_id = $1 AND role_id = $2", $administratorId, $roleId);
        $q->execute();
    }

    // deletes the administrators record
    private function deleteAdministrator(int $administratorId): ?string
    {
        $q = new QueryBuilder("DELETE FROM ".self::TABLE_NAME." WHERE id = $1", $administratorId);
        if ($username = $q->executeWithReturnField('username')) {
            return $username;
        }

        return null;
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
