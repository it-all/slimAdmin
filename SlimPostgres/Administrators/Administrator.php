<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

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
}
