<?php
declare(strict_types=1);

namespace Domain\Administrators;

class Administrator
{
    /* int */
    private $id;
    
    /* string (nullable) */
    private $name;
    
    /* string */
    private $username;
    
    /* string */
    private $role;

    public function __construct(int $id, ?string $name, string $username, string $role)
    {
        $this->id = $id;
        $this->name = $name;
        $this->username = $username;
        $this->role = $role;
    }
    
    
    // getters
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): ?string
    {
        return $this->name;
    }
    
    public function getUsername(): string
    {
        return $this->username;
    }
    
    public function getRole(): string
    {
        return $this->role;
    }
}
