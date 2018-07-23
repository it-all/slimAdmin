<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authorization;

use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\App;

/* There are two methods of Authorization: either by minimum permission, where the user role equal to or better than the minimum permission is authorized (in this case, the permission is a string). Or by a permission set, where the user role in the set of authorized permissions is authorized (permission is an array) */
class AuthorizationService
{
    private $topRole;
    private $functionalityPermissions;
    private $rolesMapper;

    public function __construct(string $topRole, array $functionalityPermissions)
    {
        $this->topRole = $topRole;
        $this->functionalityPermissions = $functionalityPermissions;
        $this->rolesMapper = RolesMapper::getInstance();
        if (!$this->validateRole($topRole)) {
            throw new \Exception("Invalid top role: $topRole");
        }
    }

    public function getTopRole(): string
    {
        return $this->topRole;
    }

    public function hasTopRole(): bool
    {
        return $this->hasRole($this->topRole);
    }

    // $functionality like 'marketing' or 'marketing.index'
    // the return value can either be a string or an array, based on configuration. See comment at the top of class for info.
    // if not found as an exact match or category match, the base (least permission) role is returned
    public function getPermissions(?string $functionality)
    {
        if ($functionality === null) {
            return $this->rolesMapper->getBaseRole();
        }

        if (!isset($this->functionalityPermissions[$functionality])) {
            // no exact match, so see if there are multiple terms and first term matches
            $fParts = explode('.', $functionality);
            if (count($fParts) > 1 && isset($this->functionalityPermissions[App::getRouteName(true, $fParts[0])])) {
                return $this->functionalityPermissions[App::getRouteName(true, $fParts[0])];
            }

            // no matches
            return $this->rolesMapper->getBaseRole();
        }

        return $this->functionalityPermissions[$functionality];
    }

    public function getAdministratorRoles(): array
    {
        return $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES];
    }

    // checks whether current administrator session has given role
    public function hasRole(string $role): bool
    {
        if (!$this->validateRole($role)) {
            throw new \Exception("Invalid role $role");
        }

        foreach ($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES] as $roleId => $roleInfo) {
            if ($role == $roleInfo[App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME]) {
                return true;
            }
        }

        return false;
    }

    // if any role of the session administrator meet or exceed the minimum role level, return true. otherwise, return false
    // note that level 1 is the greatest role permission level
    private function checkMinimum(int $minimumRoleLevel): bool
    {

        if (!isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES])) {
            return false;
        }

        foreach ($this->getAdministratorRoles() as $roleId => $roleInfo) {
            if ($roleInfo[App::SESSION_ADMINISTRATOR_KEY_ROLES_LEVEL] <= $minimumRoleLevel) {
                return true;
            }
        }

        return false;
    }

    // validates role to be in database roles.role
    public function validateRole(string $role): bool
    {
        return in_array($role, $this->rolesMapper->getRoleNames());
    }

    // checks if logged in administrator has a role that is in the array of authorized roles
    private function checkRoleSet(array $authorizedRoles): bool
    {
        foreach ($authorizedRoles as $authorizedRole) {
            if (!is_string($authorizedRole)) {
                throw new \Exception("Invalid role type, must be strings");
            }
            if (!$this->validateRole($authorizedRole)) {
                throw new \Exception("Invalid role $authorizedRole");
            }
        }

        foreach ($this->getAdministratorRoles() as $roleId => $roleInfo) {
            if (in_array($roleInfo[App::SESSION_ADMINISTRATOR_KEY_ROLES_NAME], $authorizedRoles)) {
                return true;
            }
        }

        return false;
    }

    public function isAuthorized($permissions): bool
    {
        if (is_string($permissions)) { // a role
            return $this->checkMinimum($this->rolesMapper->getLeveForRole($permissions));
        } elseif (is_array($permissions)) { // an array of roles
            return $this->checkRoleSet($permissions);
        } else {
            throw new \Exception("Invalid permissions: $permissions");
        }
    }

    // note, returns false if the minimum permission for $functionality is not defined
    public function isFunctionalityAuthorized(string $functionality): bool
    {
        return $this->isAuthorized($this->getPermissions($functionality));
    }
}
