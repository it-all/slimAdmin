<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions\View\Forms;

use SlimPostgres\Administrators\Roles\Permissions\Model\Permission;
use \SlimPostgres\Forms\FormHelper;
use Slim\Container;

class PermissionUpdateForm extends PermissionForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formMethod = 'put';
        parent::__construct($formAction, $container, $fieldValues);
    }

    /** note could subclass here for initial get form but simpler to just add this fn */
    public function setFieldValuesToPermission(Permission $permission)
    {
        parent::setFieldValues([
            parent::PERMISSION_FIELD_NAME => $permission->getPermissionName(),
            parent::DESCRIPTION_FIELD_NAME => $permission->getDescription(),
            parent::ROLES_FIELDSET_NAME => $permission->getRoleIds(),
            parent::ACTIVE_FIELD_NAME => $permission->getActive(),
        ]);
    }

    protected function getNodes(): array 
    {
        $nodes = parent::getNodes();
        $nodes[] = FormHelper::getPutMethodField();
        return $nodes;
    }
}
