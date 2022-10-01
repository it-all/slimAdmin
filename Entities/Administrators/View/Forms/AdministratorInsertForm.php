<?php
declare(strict_types=1);

namespace Entities\Administrators\View\Forms;

use Psr\Container\ContainerInterface as Container;
use Entities\Administrators\View\Forms\AdministratorForm;

class AdministratorInsertForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, ?array $fieldValues = null)
    {
        $this->arePasswordFieldsRequired = true;
        parent::__construct($formAction, $container, false, $fieldValues);
        parent::setPasswordLabel();
    }
}
