<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Forms;

use Slim\Http\Request;
use Slim\Container;
use SlimPostgres\App;
use SlimPostgres\Administrators\AdministratorsMapper;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\BaseMVC\View\Forms;
use SlimPostgres\BaseMVC\View\Form as BaseForm;
use SlimPostgres\Forms\FormHelper;
use It_All\FormFormer\Form;
use It_All\FormFormer\Fieldset;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use SlimPostgres\Database\Postgres;

abstract class AdministratorForm extends BaseForm implements Forms
{
    protected $formAction;

    /** @var string 'put' or 'post'
     *  if 'put' the hidden put method field is inserted at the beginning of the form
     */
    protected $formMethod;
    protected $csrf;
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
    
    public function __construct(string $formAction, Container $container, bool $isPutMethod = false, array $fieldValues = [])
    {
        parent::__construct($formAction, $container, $isPutMethod);
        $this->authorization = $container->authorization;
        $this->mapper = AdministratorsMapper::getInstance();
        $this->setFieldValues($fieldValues);
    }

    protected function setPasswordLabel(string $midText = '') 
    {
        $this->passwordLabel = "Password $midText <a href='https://www.schneier.com/blog/archives/2014/03/choosing_secure_1.html' target='_blank'>info</a>";
    }

    protected function setFieldValues(array $fieldValues = [])
    {
        $this->nameValue = (isset($fieldValues[self::NAME_FIELD_NAME])) ? $fieldValues[self::NAME_FIELD_NAME] : '';

        $this->usernameValue = (isset($fieldValues[self::USERNAME_FIELD_NAME])) ? $fieldValues[self::USERNAME_FIELD_NAME] : '';

        $this->passwordValue = (isset($fieldValues[self::PASSWORD_FIELD_NAME])) ? $fieldValues[self::PASSWORD_FIELD_NAME] : '';

        $this->passwordConfirmationValue = (isset($fieldValues[self::PASSWORDCONFIRM_FIELD_NAME])) ? $fieldValues[self::PASSWORDCONFIRM_FIELD_NAME] : '';

        $this->rolesValue = (isset($fieldValues[self::ROLES_FIELDSET_NAME])) ? $fieldValues[self::ROLES_FIELDSET_NAME] : [];

        /** this must be set to a bool. it may come in as "on" or may not be set. set to default if not set, or false if already false otherwise true */
        if (!isset($fieldValues[self::ACTIVE_FIELD_NAME])) {
            $this->activeValue = self::DEFAULT_ACTIVE_VALUE;
        } else {
            $this->activeValue = ($fieldValues[self::ACTIVE_FIELD_NAME] === false) ? false : true;
        }
    }

    private function getNameField()
    {
        $nameField = DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::NAME_FIELD_NAME), null, $this->nameValue);
        return $nameField;
    }

    private function getUsernameField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::USERNAME_FIELD_NAME), null, $this->usernameValue);
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
                'id' => self::ROLES_FIELDSET_NAME . $roleData['role'],
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
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::ACTIVE_FIELD_NAME), null, Postgres::convertBoolToPostgresBool($this->activeValue));
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
