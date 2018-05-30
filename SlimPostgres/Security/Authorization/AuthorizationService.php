<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authorization;

use Domain\Administrators\Roles\RolesModel;
use SlimPostgres\App;

/* There are two methods of Authorization: either by minimum permission, where the user role equal to or better than the minimum permission is authorized (in this case, the permission is a string). Or by a permission set, where the user role in the set of authorized permissions is authorized (permission is an array) */
class AuthorizationService
{
    private $functionalityPermissions;
    private $roles;
    private $baseRole;

    public function __construct(array $functionalityPermissions, string $defaultAdminRole)
    {
        $this->functionalityPermissions = $functionalityPermissions;
        $rolesModel = new RolesModel($defaultAdminRole);
        $this->roles = $rolesModel->getRoles();
        $this->baseRole = $rolesModel->getBaseRole();
    }

    // $functionality like 'marketing' or 'marketing.index'
    // the return value can either be a string or an array, based on configuration. See comment at the top of class for info.
    // if not found as an exact match or category match, the base (least permission) role is returned
    public function getPermissions(string $functionality)
    {
        if (!isset($this->functionalityPermissions[$functionality])) {

            // no exact match, so see if there are multiple terms and first term matches
            $fParts = explode('.', $functionality);
            if (count($fParts) > 1 && isset($this->functionalityPermissions[App::getRouteName(true, $fParts[0])])) {
                return $this->functionalityPermissions[App::getRouteName(true, $fParts[0])];
            }

            // no matches
            return $this->baseRole;
        }

        return $this->functionalityPermissions[$functionality];
    }

    public function getUserRole(): ?string
    {
        $userRole = $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLE];

        if (!in_array($userRole, $this->roles)) {
            unset($_SESSION[App::SESSION_KEY_ADMINISTRATOR]); // force logout
            return null;
        }

        return $userRole;
    }

    private function checkMinimum(string $minimumRole): bool
    {
        if (!in_array($minimumRole, $this->roles)) {
            throw new \Exception("Invalid role: $minimumRole");
        }
        if (!isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLE])) {
            return false;
        }

        if (!$userRole = $this->getUserRole()) {
            return false;
        }

        return array_search($userRole, $this->roles) <= array_search($minimumRole, $this->roles);
    }

    private function checkSet(array $authorizedRoles): bool
    {
        foreach ($authorizedRoles as $authorizedRole) {
            if (!is_string($authorizedRole)) {
                throw new \Exception("Invalid role type, must be strings");
            }
            if (!in_array($authorizedRole, $this->roles)) {
                throw new \Exception("Invalid role $authorizedRole");
            }
        }

        if (!$userRole = $this->getUserRole()) {
            return false;
        }

        return in_array($userRole, $authorizedRoles);
    }

    public function check($permissions): bool
    {
        if (is_string($permissions)) {
            return $this->checkMinimum($permissions);
        } elseif (is_array($permissions)) {
            return $this->checkSet($permissions);
        } else {
            throw new \Exception('Invalid permissions');
        }
    }

    // note, returns false if the minimum permission for $functionality is not defined
    public function checkFunctionality(string $functionality): bool
    {
        if (!$permissions = $this->getPermissions($functionality)) {
            return false;
        }

        return $this->check($permissions);
    }
}
