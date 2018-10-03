<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Forms;

use Slim\Container;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Forms\FormHelper;

class AdministratorUpdateForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formMethod = 'put';
        $this->arePasswordFieldsRequired = false; // allow to leave blank to keep existing ps
        parent::__construct($formAction, $container, $fieldValues);
        parent::setPasswordLabel('[leave blank to keep existing password]');
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

    protected function getNodes(): array 
    {
        $nodes = parent::getNodes();
        $nodes[] = FormHelper::getPutMethodField();
        return $nodes;
    }
}
