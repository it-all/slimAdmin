<?php
declare(strict_types=1);

namespace Entities\Permissions\Model;

use Entities\Permissions\View\Forms\PermissionForm;
use Infrastructure\Utilities\ValitronValidatorExtension;
use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\Security\Authorization\AuthorizationService;

class PermissionsValidator extends ValitronValidatorExtension
{
    // if this is for an update there must be changed fields
    public function __construct(array $inputData, array $changedFieldValues = [])
    {
        $fields = [PermissionForm::TITLE_FIELD_NAME, PermissionForm::DESCRIPTION_FIELD_NAME, PermissionForm::ROLES_FIELDSET_NAME];
        /** note, roles is an array field but empty arrays ([]) pass required validation, so if empty set null to fail */
        if (is_array($inputData['roles']) && empty($inputData['roles'])) {
            $inputData['roles'] = null;
        }
        parent::__construct($inputData, $fields);

        $permissionsTableMapper = PermissionsTableMapper::getInstance();

        // bool - either inserting or !inserting (updating)
        $inserting = count($changedFieldValues) == 0;

        // define unique column rule to be used in certain situations below
        $this->addUniqueRule();

        $this->rule('required', ['title']);

        // unique column rule for permission if it has changed
        if ($inserting || array_key_exists('title', $changedFieldValues)) {
            $this->rule('unique', 'title', $permissionsTableMapper->getColumnByName('title'), $this);
        }

        // all selected roles must be in roles table
        $this->rule('array', 'roles');
        $rolesTableMapper = RolesTableMapper::getInstance();
        $this->rule('in', 'roles.*', array_keys($rolesTableMapper->getRoles())); // role ids
    }
}
