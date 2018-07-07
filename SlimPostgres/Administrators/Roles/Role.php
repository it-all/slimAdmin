<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

// model 
class Role 
{
    private $id;
    private $roleName;
    private $level;

    public function __construct(int $id, string $roleName, int $level)
    {
        $this->id = $id;
        $this->roleName = $roleName;
        $this->level = $level;
    }

    public function getId(): int 
    {
        return $this->id;
    }

    public function getRoleName(): string 
    {
        return $this->roleName;
    }

    public function getLevel(): int 
    {
        return $this->level;
    }
}
