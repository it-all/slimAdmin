<?php
declare(strict_types=1);

namespace SlimPostgres\Entities\Administrators\View\Forms;

use Slim\Container;
use SlimPostgres\Entities\Administrators\View\Forms\AdministratorForm;

class AdministratorInsertForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->arePasswordFieldsRequired = true;
        parent::__construct($formAction, $container, false, $fieldValues);
        parent::setPasswordLabel();
    }
}
