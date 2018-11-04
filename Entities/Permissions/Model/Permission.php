<?php
declare(strict_types=1);

namespace Entities\Permissions\Model;

use Infrastructure\BaseEntity\BaseMVC\Model\ListViewModels;
use Infrastructure\Database\Postgres;
use Infrastructure\Functions;

/** model */ 
class Permission implements ListViewModels
{
    /** @var int */
    private $id;

     /** @var string */
    private $title;

    /** @var string|null */
    private $description; 
    
    /** @var bool */
    private $active; 

    /** @var \DateTimeImmutable */
    private $created; 

    /** @var \SlimPostgres\Administrators\Roles[] an array of assigned role objects */
    private $roles; 

    /** @var int[] an array of assigned role ids */
    private $roleIds;

    /** @var string[] an array of basic system permissions which should not be deleted or changed */
    const UNDELETABLE = [
        EVENTS_VIEW_RESOURCE,
        ADMINISTRATORS_VIEW_RESOURCE,
        ADMINISTRATORS_INSERT_RESOURCE,
        ADMINISTRATORS_UPDATE_RESOURCE,
        ADMINISTRATORS_DELETE_RESOURCE,
        ROLES_VIEW_RESOURCE,
        ROLES_INSERT_RESOURCE,
        ROLES_UPDATE_RESOURCE,
        ROLES_DELETE_RESOURCE,
        PERMISSIONS_VIEW_RESOURCE,
        PERMISSIONS_INSERT_RESOURCE,
        PERMISSIONS_UPDATE_RESOURCE,
        PERMISSIONS_DELETE_RESOURCE,
    ];

    public function __construct(int $id, string $title, ?string $description, bool $active, \DateTimeImmutable $created, array $roles)
    {
        // validate roles array is array of role objects
        if (count($roles) == 0) {
            throw new \InvalidArgumentException("Roles cannot be empty for permission (id $id)");
        }
        foreach ($roles as $role) {
            if (get_class($role) != 'Entities\Roles\Model\Role') {
                throw new \InvalidArgumentException("Invalid role in roles");
            }
        }
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->active = $active;
        $this->created = $created;
        $this->roles = $roles;
        $this->setRoleIds();
    }

    public function getId(): int 
    {
        return $this->id;
    }

    public function getTitle(): string 
    {
        return $this->title;
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
            'title' => $this->title,
            'description' => $this->description,
            'roles' => $this->getRolesString(),
            'active' => Postgres::convertBoolToPostgresBool($this->active), // send 't' / 'f'
            'created' => $this->created->format('Y-m-d'),
        ];
    }

    public function getRoles(): array 
    {
        return $this->roles;
    }

    public function getRoleIds(): array 
    {
        return $this->roleIds;
    }

    private function setRoleIds() 
    {
        $roleIds = [];
        foreach ($this->roles as $role) {
            $roleIds[] = $role->getid();
        }
        $this->roleIds = $roleIds;
    }

    public function hasRole(int $roleId): bool 
    {
        return in_array($roleId, $this->roleIds);
    }
    
    public function getRolesString(): string
    {
        $rolesString = "";
        foreach ($this->roles as $role) {
            $rolesString .= $role->getRoleName().", ";
        }
        $rolesString = Functions::removeLastCharsFromString($rolesString, 2);
        return $rolesString;
    }

    /** whether model is allowed to be updated */
    public function isUpdatable(): bool
    {
        return (PermissionsTableMapper::getInstance())->isUpdatable();
    }

    /** whether this model is allowed to be deleted */
    public function isDeletable(): bool
    {
        if (in_array($this->title, self::UNDELETABLE)) {
            return false;
        }
        return (PermissionsTableMapper::getInstance())->isDeletable();
    }

    public function getUniqueId(): ?string
    {
        return (string) $this->id;
    }
}
