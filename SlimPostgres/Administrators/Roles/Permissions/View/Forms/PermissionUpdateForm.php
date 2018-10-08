<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions\View\Forms;

use SlimPostgres\Administrators\Roles\Permissions\Model\Permission;
use Slim\Container;

class PermissionUpdateForm extends PermissionForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        parent::__construct($formAction, $container, true, $fieldValues);
    }

    /** note could subclass here for initial get form but simpler to just add this fn */
    public function setFieldValuesToPermission(Permission $permission)
    {
        parent::setFieldValues([
            parent::TITLE_FIELD_NAME => $permission->getTitle(),
            parent::DESCRIPTION_FIELD_NAME => $permission->getDescription(),
            parent::ROLES_FIELDSET_NAME => $permission->getRoleIds(),
            parent::ACTIVE_FIELD_NAME => $permission->getActive(),
        ]);
    }
}
