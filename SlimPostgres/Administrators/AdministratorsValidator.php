<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Database\DatabaseTableValidation;
use SlimPostgres\Utilities\ValitronValidatorExtension;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Security\Authorization\AuthorizationService;

class AdministratorsValidator extends ValitronValidatorExtension
{
    // if this is for an update there must be changed fields
    public function __construct(array $inputData, AuthorizationService $authorization, array $changedFieldValues = [])
    {
        $fields = ['name', 'username', 'password', 'password_confirm', 'roles'];
        parent::__construct($inputData, $fields);

        $administratorsMapper = AdministratorsMapper::getInstance();

        // bool - either inserting or !inserting (updating)
        $inserting = count($changedFieldValues) == 0;

        // define unique column rule to be used in certain situations below
        self::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
            if (!$params[1]->errors($field)) {
                return !$params[0]->recordExistsForValue($value);
            }
            return true; // skip validation if there is already an error for the field
        }, 'Already exists.');

        $this->rule('required', ['name', 'username', 'roles']);
        $this->rule('regex', 'name', '%^[a-zA-Z\s]+$%')->message('must be letters and spaces only');
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

        // all selected roles must be in roles table
        $this->rule('array', 'roles');
        $rolesMapper = RolesMapper::getInstance();
        $this->rule('in', 'roles.*', array_keys($rolesMapper->getRoles())); // role ids

        // non-top-dogs cannot assign top-dog role to themselves or other non-top-dogs
        // and cannot unassign top role
        if (!$authorization->hasTopRole()) {

            $topRoleId = $authorization->getTopRoleId();

            $this->rule('notIn', 'roles.*', [$topRoleId])->message('No permission to set '.$authorization->getTopRole());

            if (!$inserting) {
                if (isset($changedFieldValues['roles']['remove']) && in_array($topRoleId, $changedFieldValues['roles']['remove'])) {
                    $this->rule('in', 'roles.*', [$topRoleId])->message('No permission to unset '.$authorization->getTopRole());
                }
            }
        }

        
    }
}
