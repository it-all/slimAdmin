<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Forms;

use Slim\Http\Request;
use Slim\Container;
use SlimPostgres\App;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use It_All\FormFormer\Form;
use It_All\FormFormer\Fieldset;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use SlimPostgres\Database\Postgres;

abstract class AdministratorForm
{
    private $formAction;

    /** @var string 'put' or 'post'
     *  if 'put' the hidden put method field is inserted at the beginning of the form
     */
    protected $formMethod;
    private $csrf;
    private $authorization;
    private $mapper;
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

    public static function getFields(): array
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
    
    public function __construct(string $formAction, Container $container, array $fieldValues = [])
    {
        $this->formAction = $formAction;
        $this->csrf = $container->csrf;
        $this->authorization = $container->authorization;
        $this->mapper = AdministratorsMapper::getInstance();
        $this->setFieldValues($fieldValues);
    }

    protected function setFieldValues(array $fieldValues = [])
    {
        $this->nameValue = (isset($fieldValues[self::NAME_FIELD_NAME])) ? $fieldValues[self::NAME_FIELD_NAME] : '';

        $this->usernameValue = (isset($fieldValues[self::USERNAME_FIELD_NAME])) ? $fieldValues[self::USERNAME_FIELD_NAME] : '';

        $this->passwordValue = (isset($fieldValues[self::PASSWORD_FIELD_NAME])) ? $fieldValues[self::PASSWORD_FIELD_NAME] : '';

        $this->passwordConfirmationValue = (isset($fieldValues[self::PASSWORDCONFIRM_FIELD_NAME])) ? $fieldValues[self::PASSWORDCONFIRM_FIELD_NAME] : '';

        $this->rolesValue = (isset($fieldValues[self::ROLES_FIELDSET_NAME])) ? $fieldValues[self::ROLES_FIELDSET_NAME] : [];

        $this->activeValue = (isset($fieldValues[self::ACTIVE_FIELD_NAME])) ? $fieldValues[self::ACTIVE_FIELD_NAME] : '';
    }

    private function getNameField()
    {
        $nameField = DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName('name'), null, $this->nameValue);
        return $nameField;
    }

    private function getUsernameField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName('username'), null, $this->usernameValue);
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
        $rolesMapper = RolesMapper::getInstance();
        $rolesCheckboxes = [];
        foreach ($rolesMapper->getRoles() as $roleId => $roleData) {
            $rolesCheckboxAttributes = [
                'type' => 'checkbox',
                'name' => self::ROLES_FIELDSET_NAME . '[]',
                'value' => $roleId,
                'id' => 'roles' . $roleData['role'],
                'class' => 'inlineFormField'
            ];
            // checked?
            if (isset($this->rolesValue) && in_array($roleId, $this->rolesValue)) {
                $rolesCheckboxAttributes['checked'] = 'checked';
            }
            // disabled? - if current administrator is non-top-dog disable top-dog role checkbox
            if (!$this->authorization->hasTopRole() && $roleData['role'] == $this->authorization->getTopRole()) {
                $rolesCheckboxAttributes['disabled'] = 'disabled';
            }
            $rolesCheckboxes[] = new CheckboxRadioInputField($roleData['role'], $rolesCheckboxAttributes);
        }
        
        return new Fieldset($rolesCheckboxes, [], true, 'Roles', null, FormHelper::getFieldError(self::ROLES_FIELDSET_NAME, true));
    }

    private function getActiveField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName('active'), null, Postgres::convertBoolToPostgresBool($this->activeValue));
    }

    protected function getNodes(): array 
    {
        $nodes = [];
        if ($this->formMethod == 'put') {
            $nodes[] = FormHelper::getPutMethodField();
        }
        $nodes[] = $this->getActiveField();
        $nodes[] = $this->getNameField();
        $nodes[] = $this->getUsernameField();
        $this->setPasswordFields($nodes);
        $nodes[] = $this->getRolesFieldset();

        // CSRF Fields
        $nodes[] = FormHelper::getCsrfNameField($this->csrf->getTokenNameKey(), $this->csrf->getTokenName());
        $nodes[] = FormHelper::getCsrfValueField($this->csrf->getTokenValueKey(), $this->csrf->getTokenValue());

        // Submit Field
        $nodes[] = FormHelper::getSubmitField();

        return $nodes;
    }

    public function getForm()
    {
        return new Form($this->getNodes(), ['method' => 'post', 'action' => $this->formAction], FormHelper::getGeneralError());
    }

}
