<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Forms;

use Slim\Container;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\Forms\AdministratorForm;

class AdministratorUpdateForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formMethod = 'put';
        $this->arePasswordFieldsRequired = false; // allow to leave blank to keep existing ps
        $this->passwordLabel = "Password [leave blank to keep existing password]";
        parent::__construct($formAction, $container, $fieldValues);
    }

    /** note could subclass here for initial get form but simpler to just add this fn */
    public function setFieldValuesToAdministrator(Administrator $administrator)
    {
        parent::setFieldValues([
            parent::NAME_FIELD_NAME => $administrator->getName(),
            parent::USERNAME_FIELD_NAME => $administrator->getUsername(),
            parent::PASSWORD_FIELD_NAME => '',
            parent::PASSWORDCONFIRM_FIELD_NAME => '',
            parent::ROLES_FIELDSET_NAME => $administrator->getRoleIds(),
            parent::ACTIVE_FIELD_NAME => $administrator->getActive(),
        ]);
    }
}
