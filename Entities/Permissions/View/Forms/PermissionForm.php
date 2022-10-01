<?php
declare(strict_types=1);

namespace Entities\Permissions\View\Forms;

use Psr\Container\ContainerInterface as Container;
use Entities\Permissions\Model\PermissionsTableMapper;
use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\Forms;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\Form as BaseForm;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use It_All\FormFormer\Fieldset;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use Infrastructure\Database\Postgres;

class PermissionForm extends BaseForm implements Forms
{
    protected $formAction;

    /** @var string 'put' or 'post'
     *  if 'put' the hidden put method field is inserted at the beginning of the form
     */
    private $tableMapper;
    private $permissionValue;
    private $descriptionValue;

    /** @var array */
    private $rolesValue; 

    /** @var bool */
    private $activeValue; 

    const TITLE_FIELD_NAME = 'title';
    const DESCRIPTION_FIELD_NAME = 'description';
    const ROLES_FIELDSET_NAME = 'roles';
    const ACTIVE_FIELD_NAME = 'active';

    /** bool, controls whether insert checkbox defaults to checked (true) or unchecked (false) */
    const DEFAULT_ACTIVE_VALUE = true;

    public static function getFieldNames(): array
    {
        return [
            self::TITLE_FIELD_NAME,
            self::DESCRIPTION_FIELD_NAME,
            self::ROLES_FIELDSET_NAME,
            self::ACTIVE_FIELD_NAME,
        ];
    }
    
    public function __construct(string $formAction, Container $container, bool $isPutMethod = false, ?array $fieldValues = null)
    {
        parent::__construct($formAction, $container, $isPutMethod);
        $this->authorization = $container->get('authorization');
        $this->tableMapper = PermissionsTableMapper::getInstance();
        $this->setFieldValues($fieldValues);
    }

    protected function setFieldValues(?array $fieldValues = null)
    {
        if ($fieldValues === null) {
            $this->permissionValue = '';
            $this->descriptionValue = '';
            $this->rolesValue = [];
            $this->activeValue = self::DEFAULT_ACTIVE_VALUE;
        } else {
            $this->permissionValue = $fieldValues[self::TITLE_FIELD_NAME];
            $this->descriptionValue = $fieldValues[self::DESCRIPTION_FIELD_NAME];
            $this->rolesValue = $fieldValues[self::ROLES_FIELDSET_NAME];
            $this->activeValue = $fieldValues[self::ACTIVE_FIELD_NAME];
        }
    }

    private function getTitleField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->tableMapper->getColumnByName(self::TITLE_FIELD_NAME), null, $this->permissionValue);
    }

    private function getDescriptionField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->tableMapper->getColumnByName(self::DESCRIPTION_FIELD_NAME), null, $this->descriptionValue);
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
                'id' => 'roles' . $roleName,
                'class' => 'inlineFormField'
            ];
            // checked? owner is always
            if ($roleName == TOP_ROLE || (isset($this->rolesValue) && in_array($roleId, $this->rolesValue))) {
                $rolesCheckboxAttributes['checked'] = 'checked';
                if ($roleName == TOP_ROLE) {
                    $rolesCheckboxAttributes['disabled'] = 'disabled';
                }
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
        $nodes[] = $this->getTitleField();
        $nodes[] = $this->getDescriptionField();
        $nodes[] = $this->getRolesFieldset();

        $nodes = array_merge($nodes, $this->getCommonNodes());
        return $nodes;
    }
}
