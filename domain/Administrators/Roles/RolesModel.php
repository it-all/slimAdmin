<?php
declare(strict_types=1);

namespace Domain\Administrators\Roles;

use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Queries\QueryBuilder;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;

// note that level 1 is the greatest permission
class RolesModel extends SingleTableModel
{
    /* int */
    private $defaultAdminRoleId;

    /* array */
    private $roles;

    /* int */
    private $baseRoleId;

    public function __construct(string $defaultAdminRole)
    {
        parent::__construct('roles', 'id, role, level','level');
        $this->addColumnNameConstraint('level', 'positive');
        $this->setRoles($defaultAdminRole);
    }

    public function setRoles(string $defaultAdminRole)
    {
        $this->roles = [];
        $rs = $this->select();
        $lastRoleId = '';
        while ($row = pg_fetch_array($rs)) {
            $this->roles[$row['id']] = $row['role'];

            if ($row['role'] == $defaultAdminRole) {
                $this->defaultAdminRoleId = (int) $row['id'];
            }

            $lastRoleId = (int) $row['id'];
        }

        // the last role returned is set to baseRole since order by level
        $this->baseRoleId = $lastRoleId;
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

    public function getDefaultAdminRoleId(): int
    {
        return $this->defaultAdminRoleId;
    }

    public function getDefaultAdminRole(): string
    {
        return $this->roles[$this->defaultAdminRoleId];
    }

    public static function hasAdmin(int $roleId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM administrators WHERE role_id = $1", $roleId);
        return (bool) $q->getOne();
    }

    public function getIdSelectField(array $fieldAttributes, string $fieldLabel = 'Role', ?int $selectedOption, bool $useDefaultAdminRoleIdAsSelectedIfNotProvided = true, ?string $fieldError)
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

        // alter selectedOption and fieldError to send to SelectField constructor as empty string if null
        if ($selectedOption === null) {
            $selectedOption = ($useDefaultAdminRoleIdAsSelectedIfNotProvided) ? $this->defaultAdminRoleId : '';
        }
        if ($fieldError === null) {
            $fieldError = '';
        }

        return new SelectField($rolesOptions, (string) $selectedOption, $fieldLabel, $fieldAttributes, $fieldError);
    }
}
