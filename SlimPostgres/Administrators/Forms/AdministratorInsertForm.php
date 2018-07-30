<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Forms;

use Slim\Container;
use SlimPostgres\Administrators\Forms\AdministratorForm;

class AdministratorInsertForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formMethod = 'post';
        $this->arePasswordFieldsRequired = true;
        $this->passwordLabel = "Password";
        parent::__construct($formAction, $container, $fieldValues);
    }
}
