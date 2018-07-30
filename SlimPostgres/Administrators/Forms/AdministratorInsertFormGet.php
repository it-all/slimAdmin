<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use Slim\Container;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\AdministratorInsertForm;
use SlimPostgres\Forms\FormHelper;
use It_All\FormFormer\Form;

/** this is the initial insert form (get route) with no field values populated */
class AdministratorInsertFormGet extends AdministratorInsertForm
{
    public function __construct(string $formAction, Container $container)
    {
        parent::__construct($formAction, $container);
    }

    public function getForm()
    {
        $administratorsMapper = AdministratorsMapper::getInstance();

        // $passwordLabel = 'Password';
        // $passwordFieldsRequired = true;

        $fields = $this->getFields();
        
        return new Form($fields, ['method' => 'post', 'action' => $this->formAction, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
        FormHelper::unsetFormSessionVars();;
    }

}
