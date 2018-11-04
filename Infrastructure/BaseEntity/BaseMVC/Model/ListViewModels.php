<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\Model;

use Infrastructure\Security\Authorization\AuthorizationService;
use Infrastructure\Security\Authentication\AuthenticationService;

interface ListViewModels
{
    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array;

    /** whether model is allowed to be updated (by current administrator) */
    public function isUpdatable(): bool;

    /** whether model is allowed to be deleted (by current administrator) */
    public function isDeletable(): bool;

    /** can return null ie no primary key for a single table model 
     *  not necessarily the same as primary key (ie joined table models) 
     */
    public function getUniqueId(): ?string;
}
