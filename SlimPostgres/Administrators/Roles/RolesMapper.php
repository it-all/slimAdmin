<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;
use SlimPostgres\Exceptions;

// Singleton
// note that level 1 is the greatest permission
final class RolesMapper extends TableMapper
{
    /** array role_id => [role, level] */
    private $roles;

    const TABLE_NAME = 'roles';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new RolesMapper();
        }
        return $instance;
    }

    private function __construct()
    {
        // note that the roles select must be ordered by level (ascending) for getBaseLevel() to work
        parent::__construct(self::TABLE_NAME, 'id, role, level', 'level');
        $this->addColumnNameConstraint('level', 'positive');
        $this->setRoles();
    }

    // this is called by constructor and also should be called after a change to roles from single page app to reset them.
    public function setRoles()
    {
        $this->roles = [];
        $rs = $this->select();
        while ($row = pg_fetch_array($rs)) {
            $this->roles[(int) $row['id']] = [
                'role' => $row['role'],
                'level' => $row['level']
            ];
        }
    }

    public function getRoleIdForRole(string $roleSearch): int
    {
        foreach ($this->roles as $roleId => $roleData) {
            if ($roleSearch == $roleData['role']) {
                return $roleId;
            }
        }

        throw new \Exception("Invalid role searched: $roleSearch");
    }

    public function getRoleForRoleId(int $roleIdSearch): string
    {
        foreach ($this->roles as $roleId => $roleData) {
            if ($roleIdSearch == $roleId) {
                return $roleData['role'];
            }
        }

        throw new \Exception("Invalid role id searched: $roleIdSearch");

    }

    public function getLeveForRoleId(int $roleIdSearch): int
    {
        foreach ($this->roles as $roleId => $roleData) {
            if ($roleIdSearch == $roleId) {
                return (int) $roleData['level'];
            }
        }

        throw new \Exception("Invalid role searched: $roleIdSearch");
    }

    public function getLeveForRole(string $roleSearch): int
    {
        foreach ($this->roles as $roleId => $roleData) {
            if ($roleSearch == $roleData['role']) {
                return (int) $roleData['level'];
            }
        }

        throw new \Exception("Invalid role searched: $roleSearch");
    }

    public function getObject(int $primaryKey): ?Role 
    {
        if ($record = $this->selectForPrimaryKey($primaryKey)) {
            return new Role((int) $record['id'], $record['role'], $record['level']);
        }

        return null;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getRoleNames(): array
    {
        $roleNames = [];
        foreach ($this->roles as $roleId => $roleData) {
            $roleNames[] = $roleData['role'];
        }
        return $roleNames;
    }

    // the last role is the base role since setRoles orders by level
    // note that there can be multiple roles at the same level
    public function getBaseRole(): int
    {
        return (int) end($this->roles)['role'];
    }

    // the last level is the base level since setRoles orders by level
    public function getBaseLevel(): int
    {
        return (int) end($this->roles)['level'];
    }

    public function hasAdministrator(int $roleId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM administrator_roles WHERE role_id = $1", $roleId);
        return (bool) $q->getOne();
    }

    public function getIdSelectField(array $fieldAttributes, string $fieldLabel = 'Role', ?int $selectedOption, ?string $fieldError)
    {
        // validate a provided selectedOption by verifying it is a valid role
        $selectedOptionValid = ($selectedOption === null) ? true : false;

        // create the options
        $rolesOptions = [];
        foreach ($this->getRoles() as $roleId => $roleData) {
            $rolesOptions[] = new SelectOption($roleData['role'], (string) $roleId);
            if (!$selectedOptionValid && $roleId == $selectedOption) {
                $selectedOptionValid = true;
            }
        }

        if (!$selectedOptionValid) {
            throw new \Exception("Invalid selected option $selectedOption does not exist in roles");
        }

        // alter fieldError to send to SelectField constructor as empty string if null

        if ($fieldError === null) {
            $fieldError = '';
        }

        return new SelectField($rolesOptions, (string) $selectedOption, $fieldLabel, $fieldAttributes, $fieldError);
    }

    // override for validation
    // return query result
    public function deleteByPrimaryKey($primaryKeyValue, string $returning = null) 
    {
        // make sure returning column exists
        if ($returning !== null) {
            if (null === $returnColumn = $this->getColumnByName($returning)) {
                throw new \InvalidArgumentException("Invalid return column $returning");
            }
        }

        // make sure role is not being used
        if ($this->hasAdministrator((int) $primaryKeyValue)) {
            throw new Exceptions\UnallowedActionException("Role in use: id $primaryKeyValue");
        }

        try {
            $dbResult = parent::deleteByPrimaryKey($primaryKeyValue, $returning);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            throw new Exceptions\QueryResultsNotFoundException("Role not found: id $primaryKeyValue");
        }

        return $dbResult;
    }
}
