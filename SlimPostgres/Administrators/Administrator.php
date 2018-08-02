<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsMapper;
use SlimPostgres\SystemEvents\SystemEventsMapper;
use SlimPostgres\Security\Authentication\AuthenticationService;
use SlimPostgres\Security\Authorization\AuthorizationService;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\ListViewModels;
use SlimPostgres\Database\Queries\QueryBuilder;

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

    /** array [role_id => ['roleName' => 'owner', 'roleLevel' => 1], ...] */
    private $roles;

    /** only used for certain functions. set in constructor or setAuth() */
    private $authentication;
    private $authorization;

    public function __construct(int $id, string $name, string $username, string $passwordHash, array $roles, ?AuthenticationService $authentication = null, ?AuthorizationService $authorization = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;

        $this->authentication = $authentication;
        $this->authorization = $authorization;
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

    public function getRoleIds(): array 
    {
        return array_keys($this->roles);
    }

    public function getAuthorization(): ?AuthorizationService 
    {
        return $this->authorization;
    }

    public function getChangedFieldValues(string $name, string $username, ?array $roles, bool $includePassword = true, ?string $password = null): array 
    {
        $changedFieldValues = [];

        if ($this->getName() != $name) {
            $changedFieldValues['name'] = $name;
        }
        if ($this->getUsername() != $username) {
            $changedFieldValues['username'] = $username;
        }

        if ($includePassword && !password_verify($password, $this->getPasswordHash())) {
            $changedFieldValues['passwordHash'] = $password;
        }

        // roles - only add to main array if changed
        if ($roles === null) {
            $roles = [];
        }
        $addRoles = []; // populate with ids of new roles
        $removeRoles = []; // populate with ids of former roles
        
        $currentRoles = $this->getRoles();

        // search roles to add
        foreach ($roles as $newRoleId) {
            if (!array_key_exists($newRoleId, $currentRoles)) {
                $addRoles[] = $newRoleId;
            }
        }

        // search roles to remove
        foreach ($currentRoles as $currentRoleId => $currentRoleInfo) {
            if (!in_array($currentRoleId, $roles)) {
                $removeRoles[] = $currentRoleId;
            }
        }

        if (count($addRoles) > 0) {
            $changedFieldValues['roles']['add'] = $addRoles;
        }

        if (count($removeRoles) > 0) {
            $changedFieldValues['roles']['remove'] = $removeRoles;
        }

        return $changedFieldValues;
    }

    public function getChangedFieldsString(array $changedFields): string 
    {
        $allowedChangedFieldsKeys = ['name', 'username', 'roles', 'password'];

        $changedString = "";

        foreach ($changedFields as $fieldName => $newValue) {

            // make sure only correct fields have been input
            if (!in_array($fieldName, $allowedChangedFieldsKeys)) {
                throw new \InvalidArgumentException("$fieldName not allowed in changedFields");
            }

            $oldValue = $this->{"get".ucfirst($fieldName)}();
            
            if ($fieldName == 'roles') {

                $rolesMapper = RolesMapper::getInstance();

                $addRoleIds = (isset($newValue['add'])) ? $newValue['add'] : [];
                $removeRoleIds = (isset($newValue['remove'])) ? $newValue['remove'] : [];

                // update values based on add/remove and old roles
                $updatedNewValue = "";
                $updatedOldValue = "";
                foreach ($oldValue as $roleId => $roleInfo) {
                    $updatedOldValue .= $roleInfo['roleName']." ";
                    // don't put the roles being removed into the new value
                    if (!in_array($roleId, $removeRoleIds)) {
                        $updatedNewValue .= $roleInfo['roleName']." ";
                    }
                }
                foreach ($addRoleIds as $roleId) {
                    $updatedNewValue .= $rolesMapper->getRoleForRoleId((int) $roleId) . " ";
                }
                $newValue = $updatedNewValue;
                $oldValue = $updatedOldValue;
            }

            $changedString .= " $fieldName: $oldValue => $newValue, ";
        }

        return substr($changedString, 0, strlen($changedString)-2);
    }

    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'roles' => implode(", ", $this->roles)
        ];
    }

    // returns true if this administrator has top role in roles array.
    public function hasTopRole(): bool 
    {
        if (is_null($this->authorization)) {
            throw new \Exception("Authorization must be set");
        }

        return $this->hasRole($this->authorization->getTopRole());
    }

    public function hasRole(string $roleName): bool 
    {
        $rolesMapper = RolesMapper::getInstance();
        if (!in_array($roleName, $rolesMapper->getRoleNames())) {
            throw new \InvalidArgumentException("Invalid role $roleName");
        }

        return in_array($roleName, $this->roles);
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
        
        try {
            (AdministratorsMapper::getInstance())->validateDelete($this);
        } catch (\Exception $e) {
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
}
