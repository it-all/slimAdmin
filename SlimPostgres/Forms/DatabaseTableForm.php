<?php
declare(strict_types=1);

namespace SlimPostgres\Forms;

use It_All\FormFormer\Fields\Field;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;
use It_All\FormFormer\Fields\TextareaField;
use It_All\FormFormer\Form;
use SlimPostgres\Database\DataMappers\ColumnMapper;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\DatabaseTableValidation;
use SlimPostgres\Database\Postgres;

class DatabaseTableForm extends Form
{
    private static $tableMapper;

    /** @var array of form field columns */
    private static $fieldColumns;

    /** @var array */
    private static $fieldNames;

    const TEXTAREA_COLS = 50;
    const TEXTAREA_ROWS = 5;

    public function __construct(TableMapper $databaseTableMapper, string $formAction, string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, string $databaseAction = 'insert', array $fieldData = null, bool $jsValidate = true)
    {
        self::$tableMapper = $databaseTableMapper;

        $formTagAttributes = ['method' => 'post', 'action' => $formAction];
        if (!$jsValidate) {
            $formTagAttributes['novalidate'] = 'novalidate';
        }

        /** also sets field names */
        self::setFieldColumns();

        $fields = $this->getFields($csrfNameKey, $csrfNameValue, $csrfValueKey, $csrfValueValue, $databaseAction, $fieldData);

        parent::__construct($fields, $formTagAttributes, FormHelper::getGeneralError());
    }

    private static function setFieldColumns() 
    {
        self::$fieldColumns = [];
        self::$fieldNames = [];
        foreach (self::$tableMapper->getColumns() as $column) {
            if (self::includeFieldForColumn($column)) {
                self::$fieldColumns[] = $column;
                self::$fieldNames[] = $column->getName();
            }
        }
    }

    /**
     * conditions for returning false:
     * - primary column
     */
    protected static function includeFieldForColumn(ColumnMapper $column): bool
    {
        if ($column->isPrimaryKey()) {
            return false;
        }

        return true;
    }

    /** allow access without constructing */
    public static function getFieldNames(TableMapper $databaseTableMapper): array 
    {
        if (!isset(self::$tableMapper)) {
            self::$tableMapper = $databaseTableMapper;
        } elseif (self::$tableMapper !== $databaseTableMapper) {
            throw new \InvalidArgumentException("Table mapper mismatch");
        }

        if (!isset(self::$fieldNames)) {
            self::setFieldColumns();
        }

        return self::$fieldNames;
    }

    private function getFields(string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, string $databaseAction = 'insert', array $fieldData = null)
    {
        $this->validateDatabaseActionString($databaseAction);

        $fields = [];

        foreach (self::$fieldColumns as $fieldColumn) {
            // value
            if (isset($fieldData)) {
                $columnValue = (isset($fieldData[$fieldColumn->getName()])) ? $fieldData[$fieldColumn->getName()] : ''; // sending '' instead of null takes care of checkbox fields where nothing is posted if unchecked
            } else {
                $columnValue = null;
            }

            $fields[] = $this->getFieldFromDatabaseColumn($fieldColumn, null, $columnValue);
        }

        if ($databaseAction == 'update') {
            // override post method
            $fields[] = FormHelper::getPutMethodField();
        }

        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $fields[] = FormHelper::getSubmitField();

        return $fields;
    }
    protected function validateDatabaseActionString(string $databaseAction)
    {
        if ($databaseAction != 'insert' && $databaseAction != 'update') {
            throw new \Exception("databaseAction must be insert or update ".$databaseAction);
        }
    }

    protected static function getMinMaxForIntegerTypes(ColumnMapper $column): array
    {
        switch ($column->getType()) {
            case 'smallint':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'integer':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'bigint':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'smallserial':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'serial':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'bigserial':
                $min = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'min');
                $max = DatabaseTableValidation::getDatabaseColumnValidationValue($column,'max');
                break;

            default:
                throw new \Exception("Undefined postgres integer type ".$column->getType());

        }

        return [$min, $max];

    }

    // static for access to column field only
    // valueOverride for checkboxes should be 't' or 'f' to match postgres bool
    public static function getFieldFromDatabaseColumn(
        ColumnMapper $column,
        ?bool $isRequiredOverride = null,
        ?string $valueOverride = null,
        string $labelOverride = '',
        string $inputTypeOverride = '',
        string $nameOverride = '',
        string $idOverride = ''
    )
    {
        $columnName = $column->getName();
        $value = ($valueOverride !== null) ? $valueOverride : $column->getDefaultValue();

        // set label
        if ($inputTypeOverride == 'hidden') {
            $label = '';
        } elseif (mb_strlen($labelOverride) > 0) {
            $label = $labelOverride;
        } else {
            $label = ucwords(str_replace('_', ' ', $columnName));
        }

        $fieldInfo = [
            'label' => $label,
            'attributes' => [
                'name' => ($nameOverride) ? $nameOverride : $columnName,
                'id' => ($idOverride) ? $idOverride : $columnName
            ]
        ];

        if ( ($isRequiredOverride !== null && $isRequiredOverride) || DatabaseTableValidation::getDatabaseColumnValidationValue($column, 'required') ) {
            $fieldInfo['attributes']['required'] = 'required';
        }

        // the rest of $formField is derived below based on postgres column type

        if ($column->isNumericType()) {

            $fieldInfo['tag'] = 'input';
            $fieldInfo['attributes']['type'] = 'number';

            if ($column->isIntegerType()) {
                list($fieldInfo['attributes']['min'], $fieldInfo['attributes']['max']) = self::getMinMaxForIntegerTypes($column);
            } else {
                // default for the remaining numeric fields.
                $fieldInfo['attributes']['step'] = '.01';
            }

            // value
            if (mb_strlen($value) > 0) {
                $fieldInfo['attributes']['value'] = $value;
            }
            $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));

        } else {

            switch ($column->getType()) {

                case 'text':
                    $fieldInfo['tag'] = 'textarea';
                    $fieldInfo['attributes']['cols'] = self::TEXTAREA_COLS;
                    $fieldInfo['attributes']['rows'] = self::TEXTAREA_ROWS;
                    $formField = new TextareaField($value, $fieldInfo['label'], FormHelper::getTextareaFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;

                // input fields of various types

                case 'date':
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = 'date';
                    // value
                    if (mb_strlen($value) > 0) {
                        $fieldInfo['attributes']['value'] = $value;
                    }
                    $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;


                case 'character':
                    $fieldInfo['attributes']['size'] = $column->getCharacterMaximumLength();
                    // continue into cv..
                
                case 'character varying':
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = self::getInputType($inputTypeOverride);

                    // must have max defined
                    $fieldInfo['attributes']['maxlength'] = $column->getCharacterMaximumLength();
                    // value
                    if (mb_strlen($value) > 0) {
                        $fieldInfo['attributes']['value'] = $value;
                    }

                    $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;

                case 'USER-DEFINED':
                    $options = [];
                    foreach ($column->getEnumOptions() as $option) {
                        $options[] = new SelectOption($option, $option);
                    }
                    $formField = new SelectField($options, $value, $fieldInfo['label'], $fieldInfo['attributes'], FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;

                case 'boolean':
                    // remove required attribute if it exists
                    if (isset($fieldInfo['attributes']['required'])) {
                        unset($fieldInfo['attributes']['required']);
                    }
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = 'checkbox';
                    if ($value == Postgres::BOOLEAN_TRUE) {
                        $fieldInfo['attributes']['checked'] = 'checked';
                    }
                    $formField = new CheckboxRadioInputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes'], false), FormHelper::getFieldError($fieldInfo['attributes']['name']), true);

                    break;

                case 'timestamp without time zone':
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = 'date';
                    
                    $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;

                default:
                    throw new \Exception('Undefined form field for postgres column type '.$column->getType());
            }
        }

        return $formField;
    }

    public static function getInputType(string $inputTypeOverride = '')
    {
        return (mb_strlen($inputTypeOverride) > 0) ? $inputTypeOverride : 'text';
    }
}
