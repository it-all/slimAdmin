<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;
use It_All\FormFormer\Fields\TextareaField;
use It_All\FormFormer\Form;
use Infrastructure\Database\DataMappers\ColumnMapper;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableValidation;
use Infrastructure\Database\Postgres;

class DatabaseTableForm extends Form
{
    private static $tableMapper;

    /** by default do not include in forms */
    private const DEFAULT_SKIP_COLUMNS = ['created'];

    /** @var array of form field columns */
    private static $fieldColumns;

    /** @var array */
    private static $fieldNames;

    const TEXTAREA_COLS = 60;
    const TEXTAREA_ROWS = 2;
    const INPUT_FIELD_LENGTH_STANDARD = 20;

    public function __construct(TableMapper $databaseTableMapper, string $formAction, string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, ?string $databaseAction = 'insert', ?array $fieldData = null, ?array $skipColumnNames = null, ?bool $jsValidate = true)
    {
        self::$tableMapper = $databaseTableMapper;

        $formTagAttributes = ['method' => 'post', 'action' => $formAction];
        if (!$jsValidate) {
            $formTagAttributes['novalidate'] = 'novalidate';
        }

        /** also sets field names */
        self::setFieldColumns($skipColumnNames);

        $fields = $this->getFields($csrfNameKey, $csrfNameValue, $csrfValueKey, $csrfValueValue, $databaseAction, $fieldData);

        parent::__construct($fields, $formTagAttributes, FormHelper::getGeneralError());
    }

    private static function setFieldColumns(?array $skipColumnNames = null) 
    {
        self::$fieldColumns = [];
        self::$fieldNames = [];
        foreach (self::$tableMapper->getColumns() as $column) {
            if (self::includeFieldForColumn($column, $skipColumnNames)) {
                self::$fieldColumns[] = $column;
                self::$fieldNames[] = $column->getName();
            }
        }
    }

    /**
     * conditions for returning false:
     * - primary column
     */
    protected static function includeFieldForColumn(ColumnMapper $column, ?array $skipColumnNames = null): bool
    {
        if ($column->isPrimaryKey() && $column->getIsNextVal()) {
            // primary key with a default nextval() ie sequence
            return false;
        }
        
        $notIncluded = ($skipColumnNames !== null) ? array_merge(self::DEFAULT_SKIP_COLUMNS, $skipColumnNames) : self::DEFAULT_SKIP_COLUMNS;

        if (in_array($column->getName(), $notIncluded)) {
            return false;
        }

        return true;
    }

    /** allow access without constructing */
    public static function getFieldColumns(TableMapper $databaseTableMapper): array 
    {
        if (!isset(self::$tableMapper)) {
            self::$tableMapper = $databaseTableMapper;
        } elseif (self::$tableMapper !== $databaseTableMapper) {
            throw new \InvalidArgumentException("Table mapper mismatch");
        }

        if (!isset(self::$fieldColumns)) {
            self::setFieldColumns();
        }

        return self::$fieldColumns;
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

        $nodes = [];

        foreach (self::$fieldColumns as $fieldColumn) {
            // value
            if (isset($fieldData)) {
                $columnValue = (isset($fieldData[$fieldColumn->getName()])) ? $fieldData[$fieldColumn->getName()] : ''; // sending '' instead of null takes care of checkbox fields where nothing is posted if unchecked
            } else {
                $columnValue = null;
            }

            $nodes[] = $this->getFieldFromDatabaseColumn($fieldColumn, null, $columnValue);
        }

        if ($databaseAction == 'update') {
            // override post method
            $nodes[] = FormHelper::getPutMethodField();
        }

        $nodes[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $nodes[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);
        $nodes[] = FormHelper::getSubmitField();

        return $nodes;
    }

    protected function validateDatabaseActionString(string $databaseAction)
    {
        if ($databaseAction != 'insert' && $databaseAction != 'update') {
            throw new \Exception("databaseAction must be insert or update ".$databaseAction);
        }
    }

    /** these huge values do not seem to play nicely with browsers, breaking the up/down arrows on the field */
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
        $value = $valueOverride ?? $column->getDefaultValue() ?? ''; // empty string instead of null

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

            if (!$column->isIntegerType()) {
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
                    if (mb_strlen($value) > 0) {
                        $fieldInfo['attributes']['value'] = $value;
                    }
                    $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;


                case 'character':
                    $fieldInfo['attributes']['size'] = $column->getCharacterMaximumLength();
                    // continue into cv..
                
                case 'character varying':
                    $fieldInfo['attributes']['size'] = $column->getCharacterMaximumLength() < self::INPUT_FIELD_LENGTH_STANDARD ? $column->getCharacterMaximumLength() : self::INPUT_FIELD_LENGTH_STANDARD;
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = self::getInputType($inputTypeOverride);

                    // must have max defined
                    $fieldInfo['attributes']['maxlength'] = $column->getCharacterMaximumLength();
                    if (mb_strlen($value) > 0) {
                        $fieldInfo['attributes']['value'] = $value;
                    }

                    $formField = new InputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes']), FormHelper::getFieldError($fieldInfo['attributes']['name']));
                    break;

                case 'USER-DEFINED':
                    $options = [];
                    if ($column->getIsNullable()) {
                        $options[] = new SelectOption('', '');
                    }
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

                case 'timestamp':
                case 'timestamp without time zone':
                case 'timestamp with time zone':
                    $fieldInfo['tag'] = 'input';
                    $fieldInfo['attributes']['type'] = 'datetime-local';
                    if (mb_strlen($value) > 0) {
                        // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
                        $dtValue = substr($value, 0, 10) . 'T' . substr($value, 11, 5); // ie '2018-06-12T19:30';
                        $fieldInfo['attributes']['value'] = $dtValue;
                    }
                    
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
