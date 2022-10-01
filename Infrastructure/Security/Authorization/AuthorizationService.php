<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authorization;

use Entities\Administrators\Model\Administrator;
use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Exceptions;

use Infrastructure\SlimAdmin;

class AuthorizationService
{
    /** $resource matches permission.title */
    public function isAuthorized(string $resource): bool
    {
        // get permission model object
        if (null === $permission = (PermissionsEntityMapper::getInstance())->getObjectByTitle($resource, true)) {
            throw new Exceptions\QueryResultsNotFoundException("Permission not found for: $resource");
        }

        // authorized if administrator has at least one role assigned to permission 
        return $this->getLoggedInAdministrator()->hasOneRoleByObject($permission->getRoles());
    }

    /** should not be called by resources that don't require authentication */
    public function getLoggedInAdministrator(): Administrator 
    {
        if (!isset($_SESSION[SlimAdmin::SESSION_KEY_ADMINISTRATOR_ID])) {
            throw new \Exception("No one is logged in");
        }

        if (null === $administrator = (AdministratorsEntityMapper::getInstance())->getObjectById($_SESSION[SlimAdmin::SESSION_KEY_ADMINISTRATOR_ID])) {
            unset($_SESSION[SlimAdmin::SESSION_KEY_ADMINISTRATOR_ID]); /** remove for security */
            throw new \Exception("Invalid administrator id ".$_SESSION[SlimAdmin::SESSION_KEY_ADMINISTRATOR_ID]." in session");
        }

        return $administrator;
    }

    public function hasTopRole(): bool
    {
        return $this->getLoggedInAdministrator()->hasTopRole();
    }
}
