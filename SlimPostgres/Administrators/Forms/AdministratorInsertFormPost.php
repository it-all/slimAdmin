<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use Slim\Container;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\AdministratorInsertForm;
use SlimPostgres\Forms\FormHelper;
use It_All\FormFormer\Form;

/** this is the insert form (post route) with field values populated if they exist 
 *  ie the form was submitted but invalid
 */
class AdministratorInsertFormPost extends AdministratorInsertForm
{
    private $nameValue;
    private $usernameValue;

    public function __construct(string $formAction, Container $container, string $nameValue = '', string $usernameValue = '')
    {
        $this->nameValue = $nameValue;
        $this->usernameValue = $usernameValue;
        parent::__construct($formAction, $container);
    }

    public function getForm()
    {
        $administratorsMapper = AdministratorsMapper::getInstance();

        // $passwordLabel = 'Password';
        // $passwordFieldsRequired = true;

        $fields = $this->getFields($this->nameValue, $this->usernameValue);
        
        return new Form($fields, ['method' => 'post', 'action' => $this->formAction, 'novalidate' => 'novalidate'], FormHelper::getGeneralError());
        FormHelper::unsetFormSessionVars();;
    }

}
