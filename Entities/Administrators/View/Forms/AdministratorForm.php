<?php
declare(strict_types=1);

namespace Entities\Administrators\View\Forms;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface as Container;
use Infrastructure\SlimAdmin;
use Entities\Administrators\Model\AdministratorsTableMapper;
use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\Forms;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\Form as BaseForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use It_All\FormFormer\Form;
use It_All\FormFormer\Fieldset;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use Infrastructure\Database\Postgres;

abstract class AdministratorForm extends BaseForm implements Forms
{
    protected $formAction;

    /** @var string 'put' or 'post'
     *  if 'put' the hidden put method field is inserted at the beginning of the form
     */
    protected $formMethod;
    protected $csrf;
    private $authorization;
    private $tableMapper;
    private $nameValue;
    private $usernameValue;
    private $passwordValue;
    private $passwordConfirmationValue;
    private $rolesValue; /** @var array */
    private $activeValue; /** @var bool */
    protected $passwordLabel;
    protected $arePasswordFieldsRequired; /** @var bool */

    const NAME_FIELD_NAME = 'name';
    const USERNAME_FIELD_NAME = 'username';
    const PASSWORD_FIELD_NAME = 'password';
    const PASSWORDCONFIRM_FIELD_NAME = 'password_confirm';
    const ROLES_FIELDSET_NAME = 'roles';
    const ACTIVE_FIELD_NAME = 'active';

    /** bool, controls whether insert checkbox defaults to checked (true) or unchecked (false) */
    const DEFAULT_ACTIVE_VALUE = true;

    public static function getFieldNames(): array
    {
        return [
            self::NAME_FIELD_NAME,
            self::USERNAME_FIELD_NAME,
            self::PASSWORD_FIELD_NAME,
            self::PASSWORDCONFIRM_FIELD_NAME,
            self::ROLES_FIELDSET_NAME,
            self::ACTIVE_FIELD_NAME,
        ];
    }
    
    public function __construct(string $formAction, Container $container, bool $isPutMethod = false, ?array $fieldValues = null)
    {
        parent::__construct($formAction, $container, $isPutMethod);
        $this->authorization = $container->get('authorization');
        $this->tableMapper = AdministratorsTableMapper::getInstance();
        $this->setFieldValues($fieldValues);
    }

    protected function setPasswordLabel(string $midText = '') 
    {
        $this->passwordLabel = "Password $midText <a href='https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html' target='_blank'>info</a>";
    }

    protected function setFieldValues(?array $fieldValues = null)
    {
        if ($fieldValues === null) {
            $this->nameValue = '';
            $this->usernameValue = '';
            $this->passwordValue = '';
            $this->passwordConfirmationValue = '';
            $this->rolesValue = [];
            $this->activeValue = self::DEFAULT_ACTIVE_VALUE;
        } else {
            $this->nameValue = $fieldValues[self::NAME_FIELD_NAME];
            $this->usernameValue = $fieldValues[self::USERNAME_FIELD_NAME];
            $this->passwordValue = $fieldValues[self::PASSWORD_FIELD_NAME];
            $this->passwordConfirmationValue = $fieldValues[self::PASSWORDCONFIRM_FIELD_NAME];
            $this->rolesValue = $fieldValues[self::ROLES_FIELDSET_NAME];
            $this->activeValue = $fieldValues[self::ACTIVE_FIELD_NAME];
        }
    }

    private function getNameField()
    {
        $nameField = DatabaseTableForm::getFieldFromDatabaseColumn($this->tableMapper->getColumnByName(self::NAME_FIELD_NAME), null, $this->nameValue);
        return $nameField;
    }

    private function getUsernameField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->tableMapper->getColumnByName(self::USERNAME_FIELD_NAME), null, $this->usernameValue);
    }

    private function setPasswordFields(array &$nodes)
    {
        $passwordFieldAttributes = ['name' => self::PASSWORD_FIELD_NAME, 'id' => self::PASSWORD_FIELD_NAME, 'type' => 'password', 'value' => $this->passwordValue];
        $passwordConfirmationFieldAttributes = ['name' => self::PASSWORDCONFIRM_FIELD_NAME, 'id' => self::PASSWORDCONFIRM_FIELD_NAME, 'type' => 'password', 'value' => $this->passwordConfirmationValue];

        if ($this->arePasswordFieldsRequired) {
            $passwordFieldAttributes = array_merge($passwordFieldAttributes, ['required' => 'required']);
            $passwordConfirmationFieldAttributes = array_merge($passwordConfirmationFieldAttributes, ['required' => 'required']);
        }

        $nodes[] = new InputField($this->passwordLabel, $passwordFieldAttributes, FormHelper::getFieldError($passwordFieldAttributes['name']));

        $nodes[] = new InputField('Confirm Password', $passwordConfirmationFieldAttributes, FormHelper::getFieldError($passwordConfirmationFieldAttributes['name']));
    }

    private function getRolesFieldset() 
    {
        // Roles Checkboxes
        $rolesTableMapper = RolesTableMapper::getInstance();
        $rolesCheckboxes = [];
        foreach ($rolesTableMapper->getRoles() as $roleId => $roleName) {
            $rolesCheckboxAttributes = [
                'type' => 'checkbox',
                'name' => self::ROLES_FIELDSET_NAME . '[]',
                'value' => $roleId,
                'id' => self::ROLES_FIELDSET_NAME . $roleName,
                'class' => 'inlineFormField'
            ];
            // checked?
            if (isset($this->rolesValue) && in_array($roleId, $this->rolesValue)) {
                $rolesCheckboxAttributes['checked'] = 'checked';
            }
            // disabled? - if current administrator is non-top-dog disable top-dog role checkbox
            if (!$this->authorization->hasTopRole() && $roleName == TOP_ROLE) {
                $rolesCheckboxAttributes['disabled'] = 'disabled';
            }
            $rolesCheckboxes[] = new CheckboxRadioInputField($roleName, $rolesCheckboxAttributes);
        }
        
        return new Fieldset($rolesCheckboxes, [], true, 'Roles', null, FormHelper::getFieldError(self::ROLES_FIELDSET_NAME, true));
    }

    private function getActiveField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->tableMapper->getColumnByName(self::ACTIVE_FIELD_NAME), null, Postgres::convertBoolToPostgresBool($this->activeValue));
    }

    protected function getNodes(): array 
    {
        $nodes = [];
        
        $nodes[] = $this->getActiveField();
        $nodes[] = $this->getNameField();
        $nodes[] = $this->getUsernameField();
        $this->setPasswordFields($nodes);
        $nodes[] = $this->getRolesFieldset();

        $nodes = array_merge($nodes, $this->getCommonNodes());
        return $nodes;
    }
}
