<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View\Forms;

use Slim\Container;
use It_All\FormFormer\Form as ItAllForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

abstract class Form
{
    public function __construct(string $formAction, Container $container, bool $isPutMethod = false)
    {
        $this->formAction = $formAction;
        $this->formMethod = ($isPutMethod) ? 'put' : 'post';
        $this->csrf = $container->csrf;
    }

    abstract protected function getNodes(): array;

    private function getAttributes(): array 
    {
        return ['method' => 'post', 'action' => $this->formAction];
    }

    public function getForm(): ItAllForm
    {
        return new ItAllForm($this->getNodes(), $this->getAttributes(), FormHelper::getGeneralError());
    }

    protected function getCommonNodes(): array 
    {
        $nodes = [];

        if ($this->formMethod == 'put') {
            $nodes[] = FormHelper::getPutMethodField();
        }
        // CSRF Fields
        $nodes[] = FormHelper::getCsrfNameField($this->csrf->getTokenNameKey(), $this->csrf->getTokenName());
        $nodes[] = FormHelper::getCsrfValueField($this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());

        // Submit Field
        $nodes[] = FormHelper::getSubmitField();

        return $nodes;
    }
}
