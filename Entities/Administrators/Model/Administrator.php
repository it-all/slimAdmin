<?php
declare(strict_types=1);

namespace Entities\Administrators\Model;

use Entities\Administrators\Model\AdministratorsMapper;
use Entities\LoginAttempts\LoginAttemptsMapper;
use Entities\SystemEvents\SystemEventsMapper;
use Infrastructure\Security\Authentication\AuthenticationService;
use Infrastructure\Security\Authorization\AuthorizationService;
use Entities\Roles\Model\Role;
use Entities\Roles\Model\RolesMapper;
use Infrastructure\BaseMVC\Model\ListViewModels;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Postgres;
use Exceptions\UnallowedActionException;
use Infrastructure\Functions;

// the model of an Administrator stored in the database, which includes the record from administrators, plus an array of assigned roles.
class Administrator implements ListViewModels
{
    /** int */
    private $id;
    
    /** string (nullable) */
    private $name;

    /** string */
    private $username;

    /** string */
    private $passwordHash;

    /** @var \SlimPostgres\Administrators\Roles[] an array of assigned role objects */
    private $roles; 

    /** @var string[] array of assigned role ids */
    private $roleIds;

    /** @var string[] array of assigned role names */
    private $roleNames;

    /** bool */
    private $active;

    /** DateTimeImmutable */
    private $created;

    /** only used for certain functions. set in constructor or setAuth() */
    private $authentication;
    private $authorization;

    private $notDeletableReason;

    public function __construct(int $id, string $name, string $username, string $passwordHash, array $roles, bool $active, \DateTimeImmutable $created, ?AuthenticationService $authentication = null, ?AuthorizationService $authorization = null)
    {
        // validate roles array is array of role objects
        if (count($roles) == 0) {
            throw new \InvalidArgumentException("Roles cannot be empty for administrator (id $id)");
        }
        foreach ($roles as $role) {
            if (get_class($role) != 'Entities\Roles\Model\Role') {
                throw new \InvalidArgumentException("Invalid role in roles");
            }
        }
        $this->id = $id;
        $this->name = $name;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
        $this->active = $active;
        $this->created = $created;

        $this->authentication = $authentication;
        $this->authorization = $authorization;

        $this->setRoleIdsNames();
    }
    
    private function setRoleIdsNames() 
    {
        $roleIds = [];
        $roleNames = [];
        foreach ($this->roles as $role) {
            $roleIds[] = $role->getid();
            $roleNames[] = $role->getRoleName();
        }
        $this->roleIds = $roleIds;
        $this->roleNames = $roleNames;
    }

    public function hasRole(int $roleId): bool 
    {
        return in_array($roleId, $this->roleIds);
    }

    /** returns true if has at least one role from array of role ids */
    public function hasOneRole(array $roleIds): bool 
    {
        foreach ($roleIds as $roleId) {
            if ($this->hasRole((int) $roleId)) {
                return true;
            }
        }
        return false;
    }

    public function hasRoleByObject(Role $role): bool 
    {
        return $this->hasRole($role->getId());
    }

    public function hasOneRoleByObject(array $roles): bool 
    {
        foreach ($roles as $role) {
            if ($this->hasRoleByObject($role)) {
                return true;
            }
        }
        return false;
    }
    
    // returns true if this administrator has top role in roles array.
    public function hasTopRole(): bool 
    {
        return $this->hasRoleName(TOP_ROLE);
    }

    public function hasRoleName(string $roleName): bool 
    {
        $rolesMapper = RolesMapper::getInstance();
        if (!in_array($roleName, $rolesMapper->getRoleNames())) {
            throw new \InvalidArgumentException("Invalid role $roleName");
        }

        return in_array($roleName, $this->roleNames);
    }
        
    /** returns string of assigned roles ie role1, role2, ...  */
    public function getRolesString(): string
    {
        $rolesString = "";
        foreach ($this->roleNames as $role) {
            $rolesString .= "$role, ";
        }
        return Functions::removeLastCharsFromString($rolesString, 2);
    }

    // getters
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function getCreated(): \DateTimeImmutable
    {
        return $this->created;
    }

    public function getRoleIds(): array 
    {
        return $this->roleIds;
    }

    public function getRoleNames(): array 
    {
        return $this->roleNames;
    }

    public function getAuthorization(): ?AuthorizationService 
    {
        return $this->authorization;
    }

    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'roles' => $this->getRolesString(),
            'active' => Postgres::convertBoolToPostgresBool($this->active), // send 't' / 'f'
            'created' => $this->created->format('Y-m-d'),
        ];
    }

    public function setAuth(AuthenticationService $authentication, AuthorizationService $authorization) 
    {
        $this->authentication = $authentication;
        $this->authorization = $authorization;
    }

    /** whether model is allowed to be updated 
     *  do not allow non-owners to edit owners
     */
    public function isUpdatable(): bool
    {
        if (is_null($this->authorization)) {
            throw new \Exception("Authorization must be set");
        }

        // top dogs can update
        if ($this->authorization->hasTopRole()) {
            return true;
        }

        // non-top dogs can be updated
        if (!$this->hasTopRole()) {
            return true;
        }

        return false;
    }

    /** whether this model is allowed to be deleted 
     *  do not allow admin to delete themself or non-owners to delete owners
     */
    public function isDeletable(): bool
    {
        if (is_null($this->authorization)) {
            throw new \Exception("Authorization must be set");
        }
        
        $id = $this->getId();

        // make sure the current administrator is not deleting her/himself
        if ($this->isLoggedIn()) {
            $this->notDeletableReason = "Administrator cannot delete own account: id $id";
            return false;
        }

        // non-top dogs cannot delete top dogs
        if (!$this->getAuthorization()->hasTopRole() && $this->hasTopRole()) {
            $this->notDeletableReason = "Not authorized to delete administrator: id $id";
            return false;
        }

        // make sure there are no system events for administrator being deleted
        if ((SystemEventsMapper::getInstance())->existForAdministrator($id)) {
            $this->notDeletableReason = "System events exist for administrator: id $id";
            return false;
        }

        // make sure there are no login attempts for administrator being deleted
        $loginsMapper = LoginAttemptsMapper::getInstance();
        if ($loginsMapper->hasAdministrator($id)) {
            $this->notDeletableReason = "Login attempts exist for administrator: id $id";
            return false;
        }

        return true;
    }

    public function isId(int $id): bool 
    {
        return $id == $this->id;
    }

    public function getUniqueId(): ?string 
    {
        return (string) $this->id;
    }

    public function isLoggedIn(): bool 
    {
        if (is_null($this->authentication)) {
            throw new \Exception("Authentication must be set");
        }

        return $this->id === $this->authentication->getAdministratorId();
    }

    public function isActive(): bool 
    {
        return $this->getActive();
    }

    public function getNotDeletableReason(): ?string 
    {
        return $this->notDeletableReason;
    }
}
