<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\Logins\LoginAttemptsMapper;
use SlimPostgres\SystemEvents\SystemEventsMapper;
use SlimPostgres\Security\Authentication\AuthenticationService;

// the model of an Administrator stored in the database, which includes the record from administrators, plus an array of assigned roles.
class Administrator
{
    /* int */
    private $id;
    
    /* string (nullable) */
    private $name;

    /* string */
    private $username;

    /* string */
    private $passwordHash;

    /* array like [role_id => ['roleName' => 'owner', 'roleLevel' => 1], ...] */
    private $roles;

    public function __construct(int $id, string $name, string $username, string $passwordHash, array $roles)
    {
        $this->id = $id;
        $this->name = $name;
        $this->username = $username;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
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

    // returns array [bool deleted, ?string error]
    public function delete(AuthenticationService $authentication, SystemEventsMapper $systemEvents): array 
    {
        // make sure the current administrator is not deleting her/himself
        if ((int) $primaryKey == $authentication->getAdministratorId()) {
            throw new \Exception('You cannot delete yourself from administrators');
        }

        // make sure there are no system events for administrator being deleted
        if ($systemEvents->hasForAdmin($this->id)) {
            return [false, "System events exist"];
        }

        // make sure there are no login attempts for administrator being deleted
        $loginsMapper = LoginAttemptsMapper::getInstance();
        if ($loginsMapper->hasAdministrator($this->id)) {
            return [false, "Login attempts exist"];
        }

        $administratorsMapper = AdministratorsMapper::getInstance();
        $administratorsMapper->delete($this->id);

        return [true, null];
    }
}
