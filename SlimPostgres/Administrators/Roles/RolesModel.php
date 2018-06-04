<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Queries\QueryBuilder;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;

// note that level 1 is the greatest permission
class RolesModel extends SingleTableModel
{
    /** array */
    private $roles;

    /** int */
    private $baseRoleId;

    public function __construct()
    {
        parent::__construct('roles', 'id, role, level','level');
        $this->addColumnNameConstraint('level', 'positive');
        $this->setRoles();
    }

    private function setRoles()
    {
        $this->roles = [];
        $rs = $this->select();
        $lastRoleId = '';
        while ($row = pg_fetch_array($rs)) {
            $this->roles[(int) $row['id']] = $row['role'];
            $lastRoleId = (int) $row['id'];
        }

        // the last role returned is set to baseRole since order by level
        $this->baseRoleId = $lastRoleId;
    }

    public function getRoleIdForRole(string $roleSearch): ?int
    {
        foreach ($this->roles as $roleId => $role) {
            if ($roleSearch == $role) {
                return $roleId;
            }
        }

        return null;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getBaseRoleId(): int
    {
        return $this->baseRoleId;
    }

    public function getBaseRole()
    {
        return $this->roles[$this->baseRoleId];
    }

    public static function hasAdmin(int $roleId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM administrators WHERE role_id = $1", $roleId);
        return (bool) $q->getOne();
    }

    public function getIdSelectField(array $fieldAttributes, string $fieldLabel = 'Role', ?int $selectedOption, ?string $fieldError)
    {
        // validate a provided selectedOption by verifying it is a valid role
        $selectedOptionValid = ($selectedOption === null) ? true : false;

        // create the options
        $rolesOptions = [];
        foreach ($this->getRoles() as $roleId => $role) {
            $rolesOptions[] = new SelectOption($role, (string) $roleId);
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
}
