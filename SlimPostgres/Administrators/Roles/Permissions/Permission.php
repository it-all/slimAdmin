<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\ListViewModels;
use SlimPostgres\Database\Postgres;

// model 
class Permission implements ListViewModels
{
    private $id; /** int */
    private $permissionName; /** string */
    private $description; /** string|null */
    private $active; /** bool */
    private $created; /** DateTime */

    public function __construct(int $id, string $permissionName, ?string $description, bool $active, \DateTimeImmutable $created)
    {
        $this->id = $id;
        $this->permissionName = $permissionName;
        $this->description = $description;
        $this->active = $active;
        $this->created = $created;
    }

    public function getId(): int 
    {
        return $this->id;
    }

    public function getPermissionName(): string 
    {
        return $this->permissionName;
    }

    public function getdescription(): ?string 
    {
        return $this->description;
    }

    public function getActive(): bool 
    {
        return $this->active;
    }

    public function getCreated(): DateTime 
    {
        return $this->created;
    }

    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array
    {
        return [
            'id' => $this->id,
            'permission' => $this->permissionName,
            'description' => $this->description,
            'active' => Postgres::convertBoolToPostgresBool($this->active), // send 't' / 'f'
            'created' => $this->created->format('Y-m-d'),
        ];
    }

    /** whether model is allowed to be updated */
    public function isUpdatable(): bool
    {
        return (PermissionsMapper::getInstance())->isUpdatable($this->id);
    }

    /** whether this model is allowed to be deleted 
     *  do not allow roles in use (assigned to administrators) to be deleted
     */
    public function isDeletable(): bool
    {
        return (PermissionsMapper::getInstance())->isDeletable($this->id);
    }

    public function getUniqueId(): ?string
    {
        return (string) $this->id;
    }
}
