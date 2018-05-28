<?php
declare(strict_types=1);

namespace SlimPostgres\Forms;

use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fields\SelectField;
use It_All\FormFormer\Fields\SelectOption;
use It_All\FormFormer\Fields\TextareaField;
use It_All\FormFormer\Form;
use SlimPostgres\Database\SingleTable\DatabaseColumnModel;
use SlimPostgres\Database\SingleTable\SingleTableModel;

class DatabaseTableForm extends Form
{
    const TEXTAREA_COLS = 50;
    const TEXTAREA_ROWS = 5;

    public function __construct(SingleTableModel $databaseTableModel, string $formAction, string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue, string $databaseAction = 'insert', array $fieldData = null)
    {
        $this->validateDatabaseActionString($databaseAction);

        $fields = [];

        foreach ($databaseTableModel->getColumns() as $column) {
            if ($this->includeFieldForColumn($column, $databaseAction)) {
                // value
                if (isset($fieldData)) {
                    $columnValue = (isset($fieldData[$column->getName()])) ? $fieldData[$column->getName()] : ''; // sending '' instead of null takes care of checkbox fields where nothing is posted if unchecked
                } else {
                    $columnValue = null;
                }

                $fields[] = $this->getFieldFromDatabaseColumn($column, null, $columnValue);
            }
        }

        if ($databaseAction == 'update') {
            // override post method
            $fields[] = FormHelper::getPutMethodField();
        }

        $fields[] = FormHelper::getCsrfNameField($csrfNameKey, $csrfNameValue);
        $fields[] = FormHelper::getCsrfValueField($csrfValueKey, $csrfValueValue);

        $fields[] = FormHelper::getSubmitField();

        parent::__construct($fields, ['method' => 'post', 'action' => $formAction], FormHelper::getGeneralError());

    }

    protected function validateDatabaseActionString(string $databaseAction)
    {
        if ($databaseAction != 'insert' && $databaseAction != 'update') {
            throw new \Exception("databaseAction must be insert or update ".$databaseAction);
        }
    }

   /**
     * conditions for returning false:
     * - primary column
     */
    protected function includeFieldForColumn(DatabaseColumnModel $column): bool
    {
        if ($column->isPrimaryKey()) {
            return false;
        }

        return true;
    }

    protected static function getMinMaxForIntegerTypes(DatabaseColumnModel $column): array
    {
        switch ($column->getType()) {
            case 'smallint':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'integer':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'bigint':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'smallserial':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'serial':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            case 'bigserial':
                $min = FormHelper::getDatabaseColumnValidationValue($column,'min');
                $max = FormHelper::getDatabaseColumnValidationValue($column,'max');
                break;

            default:
                throw new \Exception("Undefined postgres integer type ".$column->getType());

        }

        return [$min, $max];

    }

    public static function getFieldFromDatabaseColumn(
        DatabaseColumnModel $column,
        bool $isRequiredOverride = null,
        string $valueOverride = null,
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
        } elseif (strlen($labelOverride) > 0) {
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

        if ( ($isRequiredOverride !== null && $isRequiredOverride) || FormHelper::getDatabaseColumnValidationValue($column, 'required')) {
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
            if (strlen($value) > 0) {
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
                    if (strlen($value) > 0) {
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
                    if (strlen($value) > 0) {
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
                    if ($value == 't' || $value == 'on') {
                        $fieldInfo['attributes']['checked'] = 'checked';
                    }
                    $formField = new CheckboxRadioInputField($fieldInfo['label'], FormHelper::getInputFieldAttributes($fieldInfo['attributes']['name'], $fieldInfo['attributes'], false), FormHelper::getFieldError($fieldInfo['attributes']['name']), true);

                    break;

                default:
                    throw new \Exception('Undefined form field for postgres column type '.$column->getType());
            }
        }

        return $formField;
    }

    public static function getInputType(string $inputTypeOverride = '')
    {
        return (strlen($inputTypeOverride) > 0) ? $inputTypeOverride : 'text';
    }
}
