<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authorization;

use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\Roles\Permissions\Model\PermissionsMapper;
use SlimPostgres\Exceptions;

use SlimPostgres\App;

class AuthorizationService
{
    /** $resource matches permission.title */
    public function isAuthorized(string $resource): bool
    {
        // get permission model object
        if (null === $permission = (PermissionsMapper::getInstance())->getObjectByTitle($resource, true)) {
            throw new Exceptions\QueryResultsNotFoundException("Permission not found for: $resource");
        }

        // authorized if administrator has at least one role assigned to permission 
        return $this->getLoggedInAdministrator()->hasOneRoleByObject($permission->getRoles());
    }

    /** should not be called by resources that don't require authentication */
    public function getLoggedInAdministrator(): Administrator 
    {
        if (!isset($_SESSION[App::SESSION_KEY_ADMINISTRATOR_ID])) {
            throw new \Exception("No one is logged in");
        }

        if (null === $administrator = (AdministratorsMapper::getInstance())->getObjectById($_SESSION[App::SESSION_KEY_ADMINISTRATOR_ID])) {
            throw new \Exception("Invalid administrator id in session");
        }

        return $administrator;
    }

    public function hasTopRole(): bool
    {
        return $this->getLoggedInAdministrator()->hasTopRole();
    }
}
