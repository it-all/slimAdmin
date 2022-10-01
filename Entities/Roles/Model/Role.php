<?php
declare(strict_types=1);

namespace Entities\Roles\Model;

// model 
class Role
{
    private $id;
    private $roleName;
    private $created;

    public function __construct(int $id, string $roleName, \DateTimeImmutable $created)
    {
        $this->id = $id;
        $this->roleName = $roleName;
        $this->created = $created;
    }

    public function getId(): int 
    {
        return $this->id;
    }

    public function getRoleName(): string 
    {
        return $this->roleName;
    }

    public function getCreated(): \DateTimeImmutable 
    {
        return $this->created;
    }

    /** whether model is allowed to be updated */
    public function isUpdatable(): bool
    {
        return (RolesTableMapper::getInstance())->isUpdatable();
    }

    /** whether this model is allowed to be deleted 
     *  do not allow roles in use (assigned to administrators) to be deleted
     */
    public function isDeletable(): bool
    {
        $rolesEntityMapper = RolesEntityMapper::getInstance();
        return !$rolesEntityMapper->hasAdministrator($this->id) && !$rolesEntityMapper->hasPermissionAssigned($this->id);
    }

    public function getUniqueId(): ?string
    {
        return (string) $this->id;
    }

    public function isTop(): bool 
    {
        return $this->getRoleName() == TOP_ROLE;
    }
}
