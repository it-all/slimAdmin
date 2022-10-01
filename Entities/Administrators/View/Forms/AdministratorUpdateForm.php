<?php
declare(strict_types=1);

namespace Entities\Administrators\View\Forms;

use Psr\Container\ContainerInterface as Container;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

class AdministratorUpdateForm extends AdministratorForm
{
    public function __construct(string $formAction, Container $container, ?array $fieldValues = null)
    {
        $this->arePasswordFieldsRequired = false; // allow to leave blank to keep existing ps
        parent::__construct($formAction, $container, true, $fieldValues);
        parent::setPasswordLabel('[leave blank to keep existing password]');
    }
}
