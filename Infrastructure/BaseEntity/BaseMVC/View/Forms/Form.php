<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View\Forms;

use Psr\Container\ContainerInterface as Container;
use It_All\FormFormer\Form as ItAllForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

// a helper class to generalize some of the common features of the It_All\FormFormer\Form class
// NOTE this should be renamed as it's not a subclass of It_All\FormFormer\Form. FormHelper is taken.
// perhaps the props and methods should just go into FormHelper...
abstract class Form
{
    private $formAction;
    private $isPutMethod;
    private $csrf;
    private $submitFieldValue;
    private $attributes; // array

    public function __construct(string $formAction, Container $container, bool $isPutMethod = false, ?string $submitFieldValue = null)
    {
        $this->formAction = $formAction;
        $this->isPutMethod = $isPutMethod;
        $this->csrf = $container->get('csrf');
        $this->submitFieldValue = $submitFieldValue;
        // note: if the isPutMethod property is true, the actual html method is still post and a put hidden field is added later
        $this->attributes = ['method' => 'post', 'action' => $this->formAction];
    }

    abstract protected function getNodes(): array;

    private function getAttributes(): array 
    {
        return $this->attributes;
    }

    protected function addAttribute(string $name, string $value)
    {
        $this->attributes[$name] = $value;
    }

    public function getForm(): ItAllForm
    {
        return new ItAllForm($this->getNodes(), $this->getAttributes(), FormHelper::getGeneralError());
    }

    protected function getCommonNodes(): array 
    {
        $nodes = [];

        if ($this->isPutMethod) {
            $nodes[] = FormHelper::getPutMethodField();
        }
        // CSRF Fields
        $nodes[] = FormHelper::getCsrfNameField($this->csrf->getTokenNameKey(), $this->csrf->getTokenName());
        $nodes[] = FormHelper::getCsrfValueField($this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());

        // Submit Field
        $nodes[] = FormHelper::getSubmitField($this->submitFieldValue);

        return $nodes;
    }
}
