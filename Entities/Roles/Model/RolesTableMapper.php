<?php
declare(strict_types=1);

namespace Entities\Roles\Model;

use Infrastructure\Database\DataMappers\TableMapper;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;
use Exceptions;

// Singleton
final class RolesTableMapper extends TableMapper
{
    /** @var array role_id => role. instead of querying the database whenever a role is needed. all roles are loaded on construction and are then easily retrievable. note that if in the future any role records were to change via javascript this array would require updating through the setRoles method in order to stay in sync. */
    private $roles;

    const TABLE_NAME = 'roles';
    const ADMINISTRATORS_JOIN_TABLE_NAME = 'administrator_roles';
    const PERMISSIONS_JOIN_TABLE_NAME = 'roles_permissions';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new RolesTableMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        parent::__construct(self::TABLE_NAME, 'id, role, created', 'id');
        $this->setRoles();
    }

    // this is called by constructor and also should be called after a change to roles from single page app to reset them.
    public function setRoles()
    {
        $this->roles = [];
        $records = $this->select();
        foreach ($records as $record) {
            $this->roles[(int) $record['id']] = $record['role'];
        }
    }

    public function getRoleIdForRole(string $roleSearch): ?int
    {
        if (!$roleId = array_search($roleSearch, $this->roles)) {
            return null;
        }
        return $roleId;
    }

    public function getRoleForRoleId(int $roleIdSearch): string
    {
        if (!array_key_exists($roleIdSearch, $this->roles)) {
            throw new \InvalidArgumentException("Invalid role id searched: $roleIdSearch");
        }
        return $this->roles[$roleIdSearch];
    }

    public function buildRole(int $id, string $role, \DateTimeImmutable $created): Role 
    {
        return new Role($id, $role, $created);
    }

    public function getObjectById(int $primaryKey): ?Role 
    {
        if (null !== $record = $this->selectForPrimaryKey($primaryKey)) {
            return $this->buildRole((int) $record['id'], $record['role'], new \DateTimeImmutable($record['created']));
        }

        return null;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getRoleNames(): array
    {
        return array_values($this->roles);
    }

    public function getIdSelectField(array $fieldAttributes, string $fieldLabel = 'Role', ?int $selectedOption, ?string $fieldError)
    {
        // validate a provided selectedOption by verifying it is a valid role
        $selectedOptionValid = ($selectedOption === null) ? true : false;

        // create the options
        $rolesOptions = [];
        foreach ($this->getRoles() as $roleId => $roleName) {
            $rolesOptions[] = new SelectOption($roleName, (string) $roleId);
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

    public function isUpdatable(): bool
    {
        return true;
    }

    public function isDeletable(): bool 
    {
        return true;
    }

    // override for validation
    public function deleteByPrimaryKey($primaryKeyValue, ?string $returnField = null): ?string
    {
        // make sure role exists and is deletable
        if (null === $role = $this->getObjectById((int) $primaryKeyValue)) {
            throw new Exceptions\QueryResultsNotFoundException("Role not found: id $primaryKeyValue");
        }

        if (!$role->isDeletable((int) $primaryKeyValue)) {
            throw new Exceptions\UnallowedActionException("Role in use: id $primaryKeyValue");
        }

        return parent::deleteByPrimaryKey($primaryKeyValue, $returnField);
    }

    /** selects roles and converts recordset to array of objects and return */
    public function getObjects(array $whereColumnsInfo = null): array 
    {
        $roles = [];

        if(null !== $records = $this->select("*", $whereColumnsInfo)) {
            foreach ($records as $record) {
                $roles[] = $this->buildRole((int) $record['id'], $record['role'], new \DateTimeImmutable($record['created']));
            }
        }

        return $roles;
    }

    /** all roles must exist or exception thrown */
    public function getRoleIdsForRoles(array $roles): array 
    {
        if (count($roles) == 0) {
            throw new \InvalidArgumentException("Roles array must be populated.");
        }
    
        $roleIds = [];

        foreach ($roles as $role) {
            if (null === $roleIds[] = $this->getRoleIdForRole($role)) {
                throw new \InvalidArgumentException("Role $role does not exist");
            }
        }
    
        return $roleIds;
    }

    /** override to ignore created column */
    public function getColumns(): array
    {
        $columns = [];
        foreach (parent::getColumns() as $column) {
            if ($column->getName() != 'created') {
                $columns[] = $column;
            }
        }
        return $columns;
    }

    public function getTopRoleId(): int 
    {
        foreach ($this->roles as $roleId => $roleName) {
            if ($roleName == TOP_ROLE) {
                return $roleId;
            }
        }
    }
}
