<?php
declare(strict_types=1);

namespace SlimPostgres\Forms;

use It_All\FormFormer\Fields\InputField;
use SlimPostgres\App;
use SlimPostgres\Database\SingleTable\DatabaseColumnModel;
use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Postgres;

class FormHelper
{
    const SESSION_ERRORS_KEY = 'formErrors';
    const GENERAL_ERROR_KEY = 'generalFormError';
    const FIELD_ERROR_CLASS = 'formFieldError';

    public static function setGeneralError(string $errorMessage)
    {
        $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] = $errorMessage;
    }

    public static function setFieldErrors(array $fieldErrors)
    {
        $_SESSION[self::SESSION_ERRORS_KEY] = $fieldErrors;
    }

    public static function getGeneralError(): string
    {
        return (isset($_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY])) ? $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] : '';
    }

    public static function getFieldError(string $fieldName): string
    {
        return (isset($_SESSION[self::SESSION_ERRORS_KEY][$fieldName])) ? $_SESSION[self::SESSION_ERRORS_KEY][$fieldName] : '';
    }

    public static function getFieldValue(string $fieldName): string
    {
        return (isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$fieldName])) ? $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$fieldName] : '';
    }

    private static function getCommonFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        $attributes = [];

        // name: use field name if supplied, otherwise addAttributes['name'] will be used if supplied
        if (strlen($fieldName) > 0) {
            $attributes['name'] = $fieldName;
            unset($addAttributes['name']);
        }

        // error class
        if (strlen(self::getFieldError($fieldName)) > 0) {
            if (array_key_exists('class', $addAttributes)) {
                $attributes['class'] = $addAttributes['class'] . " " . self::FIELD_ERROR_CLASS;
                unset($addAttributes['name']);
            } else {
                $attributes['class'] = self::FIELD_ERROR_CLASS;
            }
        }

        return array_merge($attributes, $addAttributes);
    }

    public static function getInputFieldAttributes(string $fieldName = '', array $addAttributes = [], bool $insertValue = true): array
    {
        $attributes = [];

        // value - does not overwrite if in addAttributes
        if (!array_key_exists('value', $addAttributes) && $insertValue) {
            $attributes['value'] = self::getFieldValue($fieldName);
        }
        return array_merge(self::getCommonFieldAttributes($fieldName, $addAttributes), $attributes);
    }

    public static function getTextareaFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        return self::getCommonFieldAttributes($fieldName, $addAttributes);
    }

    public static function getCsrfNameField(string $csrfNameKey, string $csrfNameValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfNameKey, 'value' => $csrfNameValue]);
    }

    public static function getCsrfValueField(string $csrfValueKey, string $csrfValueValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfValueKey, 'value' => $csrfValueValue]);
    }

    public static function getPutMethodField()
    {
        return new InputField('', ['type' => 'hidden', 'name' => '_METHOD', 'value' => 'PUT']);
    }

    public static function getSubmitField(string $value = 'Enter')
    {
        return new InputField('', ['type' => 'submit', 'name' => 'submit', 'value' => $value]);
    }

    // note: this is confusing.
    public static function getCancelField(string $value = 'Cancel')
    {
        return new InputField('', ['type' => 'submit', 'name' => 'cancel', 'value' => $value, 'onclick' => 'if(confirm(\'Press OK to cancel\nPress Cancel to cancel canceling\')){return true;}']);
    }

    public static function unsetSessionInput()
    {
        if (isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT])) {
            unset($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);
        }
    }

    public static function unsetSessionFormErrors()
    {
        if (isset($_SESSION[self::SESSION_ERRORS_KEY])) {
            unset($_SESSION[self::SESSION_ERRORS_KEY]);
        }
    }

    public static function unsetFormSessionVars()
    {
        self::unsetSessionInput();
        self::unsetSessionFormErrors();
    }

    public static function getDatabaseColumnValidationValue(DatabaseColumnModel $databaseColumnModel, string $validationType)
    {
        foreach (self::getDatabaseColumnValidation($databaseColumnModel) as $validation) {
            if (!is_array($validation) && $validation == $validationType) {
                return true;
            } elseif (is_array($validation) && $validation[0] == $validationType) {
                return $validation[1];
            }
        }

        return false;
    }

    public static function getDatabaseColumnValidation(DatabaseColumnModel $databaseColumnModel): array
    {
        $columnValidation = [];
        $columnConstraints = $databaseColumnModel->getConstraints();

        if (!$databaseColumnModel->getIsNullable()) {
            $columnValidation[] = 'required';
        }

        if ($databaseColumnModel->getCharacterMaximumLength() != null) {
            $columnValidation[] = ['lengthMax', $databaseColumnModel->getCharacterMaximumLength()];
        }

        if ($databaseColumnModel->isNumericType()) {
            if ($databaseColumnModel->isIntegerType()) {
                $columnValidation[] = 'integer';
                switch ($databaseColumnModel->getType()) {
                    case 'smallint':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::SMALLINT_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::SMALLINT_MAX];
                        break;

                    case 'integer':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::INTEGER_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::INTEGER_MAX];
                        break;

                    case 'bigint':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::BIGINT_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::BIGINT_MAX];
                        break;

                    case 'smallserial':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::SMALLSERIAL_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::SMALLSERIAL_MAX];
                        break;

                    case 'serial':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::SERIAL_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::SERIAL_MAX];
                        break;

                    case 'bigserial':
                        $minValue = (in_array('positve', $columnConstraints)) ? 1 : Postgres::BIGSERIAL_MIN;
                        $columnValidation[] = ['min', $minValue];
                        $columnValidation[] = ['max', Postgres::BIGSERIAL_MAX];
                        break;

                    default:
                        throw new \Exception("Undefined postgres integer type ".$column->getType());
                }
            } else {
                $columnValidation[] = 'numeric';
            }
        }

        return $columnValidation;
    }

    public static function getDatabaseTableValidation(SingleTableModel $databaseTableModel): array
    {
        $validation = [];
        foreach ($databaseTableModel->getColumns() as $column) {
            // primary key does not have validation
            // do not impose required validation on boolean (checkbox) fields, even though the column may be not null, allowing 'f' (unchecked) is fine but doing required validation will cause an error for unchecked condition
            $columnValidation = ($column->isPrimaryKey() || $column->isBoolean()) ? [] : self::getDatabaseColumnValidation($column);
            if (count($columnValidation) > 0) {
                $validation[$column->getName()] = $columnValidation;
            }
        }

        return $validation;
    }

    public static function getDatabaseTableValidationFields(SingleTableModel $databaseTableModel): array
    {
        $fields = [];
        foreach ($databaseTableModel->getColumns() as $column) {
            // primary key does not have validation
            // do not impose required validation on boolean (checkbox) fields, even though the column may be not null, allowing 'f' (unchecked) is fine but doing required validation will cause an error for unchecked condition
            if (!$column->isPrimaryKey() && !$column->isBoolean()) {
                $fields[] = $column->getName();
            }
        }

        return $fields;
    }
}
