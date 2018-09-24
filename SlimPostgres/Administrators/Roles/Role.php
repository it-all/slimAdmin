<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\ListViewModels;

// model 
class Role implements ListViewModels
{
    private $id;
    private $roleName;
    private $level;
    private $created;

    public function __construct(int $id, string $roleName, int $level, \DateTimeImmutable $created)
    {
        $this->id = $id;
        $this->roleName = $roleName;
        $this->level = $level;
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

    public function getLevel(): int 
    {
        return $this->level;
    }

    public function getCreated(): \DateTimeImmutable 
    {
        return $this->created;
    }

    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array
    {
        return [
            'id' => $this->id,
            'role' => $this->roleName,
            'level' => $this->level,
            'created' => $this->created->format('Y-m-d'),
        ];
    }

    /** whether model is allowed to be updated */
    public function isUpdatable(): bool
    {
        return (RolesMapper::getInstance())->isUpdatable($this->id);
    }

    /** whether this model is allowed to be deleted 
     *  do not allow roles in use (assigned to administrators) to be deleted
     */
    public function isDeletable(): bool
    {
        return (RolesMapper::getInstance())->isDeletable($this->id);
    }

    public function getUniqueId(): ?string
    {
        return (string) $this->id;
    }
}
