<?php
declare(strict_types=1);

namespace Entities\Administrators;

use Infrastructure\Database\DatabaseTableValidation;
use Infrastructure\Validation\ValitronValidatorExtension;
use Entities\Roles\Model\RolesMapper;
use Infrastructure\Security\Authorization\AuthorizationService;

class AdministratorsValidator extends ValitronValidatorExtension
{
    // if this is for an update there must be changed fields
    public function __construct(array $inputData, AuthorizationService $authorization, array $changedFieldValues = [])
    {
        $fields = ['name', 'username', 'password', 'password_confirm', 'roles'];
        /** note, roles is an array field but empty arrays ([]) pass required validation, so if empty set null to fail */
        if (is_array($inputData['roles']) && empty($inputData['roles'])) {
            $inputData['roles'] = null;
        }
        parent::__construct($inputData, $fields);

        $administratorsMapper = AdministratorsMapper::getInstance();

        // bool - either inserting or !inserting (updating)
        $inserting = count($changedFieldValues) == 0;

        // define unique column rule to be used in certain situations below
        $this->addUniqueRule();

        $this->rule('required', ['name', 'username', 'roles']);
        $this->rule('regex', 'name', '%^[a-zA-Z\s\'-]+$%')->message('only letters, apostrophes, hyphens, and spaces allowed');
        $this->rule('lengthMin', 'username', 4);
        if ($inserting || mb_strlen($inputData['password']) > 0) {
            $this->rule('required', ['password', 'password_confirm']);
            // https://stackoverflow.com/questions/8141125/regex-for-password-php
            $this->rule('regex', 'password', '%^\S*(?=\S{12,})\S*$%')->message('Must be at least 12 characters long');
            $this->rule('equals', 'password', 'password_confirm')->message('must be the same as Confirm Password');
        }

        // unique column rule for username if it has changed
        if ($inserting || array_key_exists('username', $changedFieldValues)) {
            $this->rule('unique', 'username', $administratorsMapper->getColumnByName('username'), $this);
        }

        // all roles must be in roles table
        $this->rule('array', 'roles');
        $rolesMapper = RolesMapper::getInstance();
        $this->rule('in', 'roles.*', array_keys($rolesMapper->getRoles())); // role ids

        // non-top-dogs cannot assign top-dog role to themselves or other non-top-dogs
        // and cannot unassign top role
        if (!$authorization->hasTopRole()) {

            $topRoleId = $rolesMapper->getTopRoleId();

            $this->rule('notIn', 'roles.*', [$topRoleId])->message('No permission to set '.TOP_ROLE);

            if (!$inserting) {
                if (isset($changedFieldValues['roles']['remove']) && in_array($topRoleId, $changedFieldValues['roles']['remove'])) {
                    $this->rule('in', 'roles.*', [$topRoleId])->message('No permission to unset '.TOP_ROLE);
                }
            }
        }
    }
}
