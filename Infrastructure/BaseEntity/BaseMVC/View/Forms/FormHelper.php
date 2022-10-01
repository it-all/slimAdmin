<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View\Forms;

use It_All\FormFormer\Fields\InputField;

class FormHelper
{
    const SESSION_ERRORS_KEY = 'formErrors';
    const GENERAL_ERROR_KEY = 'generalFormError';
    const FIELDS_ERROR_KEY = 'formFieldsError';
    const FIELD_ERROR_CLASS = 'formFieldError';
    const TEXT_ERROR_CLASS = 'ffErrorMsg';
    const GENERAL_ERROR_CLASS = 'generalFormError';

    public static function setGeneralError(string $errorMessage)
    {
        $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] = $errorMessage;
    }

    public static function setFieldErrors(array $fieldErrors)
    {
        $_SESSION[self::SESSION_ERRORS_KEY][self::FIELDS_ERROR_KEY] = $fieldErrors;
    }

    public static function getGeneralError(): string
    {
        return $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] ?? '';
    }

    /**
     * @param string $fieldName
     * returns empty string rather than null to be compatible with FormFormer field instantiation
     */
    public static function getFieldError(string $fieldName): string
    {
        return $_SESSION[self::SESSION_ERRORS_KEY][self::FIELDS_ERROR_KEY][$fieldName] ?? '';
    }

    private static function getCommonFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        $attributes = [];

        // name: use field name if supplied, otherwise addAttributes['name'] will be used if supplied
        if (mb_strlen($fieldName) > 0) {
            $attributes['name'] = $fieldName;
            unset($addAttributes['name']);
        }

        // error class
        if (mb_strlen(self::getFieldError($fieldName)) > 0) {
            if (array_key_exists('class', $addAttributes)) {
                $attributes['class'] = $addAttributes['class'] . " " . self::FIELD_ERROR_CLASS;
                unset($addAttributes['class']);
            } else {
                $attributes['class'] = self::FIELD_ERROR_CLASS;
            }
        }

        return array_merge($attributes, $addAttributes);
    }

    public static function getInputFieldAttributes(string $fieldName = '', array $addAttributes = [], bool $insertValue = true): array
    {
        return self::getCommonFieldAttributes($fieldName, $addAttributes);
    }

    public static function getTextareaFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        return self::getCommonFieldAttributes($fieldName, $addAttributes);
    }

    public static function getCsrfNameField(string $csrfNameKey, string $csrfNameValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfNameKey, 'value' => $csrfNameValue]);
    }

    public static function getCsrfNameFieldString(string $csrfNameKey, string $csrfNameValue): string 
    {
        return self::getCsrfNameField($csrfNameKey, $csrfNameValue)->generate();
    }

    public static function getCsrfValueField(string $csrfValueKey, string $csrfValueValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfValueKey, 'value' => $csrfValueValue]);
    }

    public static function getCsrfValueFieldString(string $csrfValueKey, string $csrfValueValue): string 
    {
        return self::getCsrfValueField($csrfValueKey, $csrfValueValue)->generate();
    }

    public static function getCsrfFieldsString(string $csrfNameKey, string $csrfNameValue, string $csrfValueKey, string $csrfValueValue): string 
    {
        return self::getCsrfNameFieldString($csrfNameKey, $csrfNameValue) . self::getCsrfValueFieldString($csrfValueKey, $csrfValueValue);
    }

    public static function getPutMethodField()
    {
        return new InputField('', ['type' => 'hidden', 'name' => '_METHOD', 'value' => 'PUT']);
    }

    public static function getSubmitField(?string $value = 'Enter')
    {
        if ($value === null) {
            $value = 'Enter';
        }
        
        return new InputField('', ['type' => 'submit', 'name' => 'submit', 'value' => $value]);
    }

    // note: this is confusing, but it's confusing to cancel a cancel.
    public static function getCancelField(string $value = 'Cancel')
    {
        return new InputField('', ['type' => 'submit', 'name' => 'cancel', 'value' => $value, 'onclick' => 'if(confirm(\'Press OK to cancel\nPress Cancel to cancel canceling\')){return true;}']);
    }

    public static function getDateField(string $name, ?string $value = '', ?string $id = null, ?string $label = ''): InputField
    {
        $idAttribute = $id ?? $name;
        $attributes = [
            'type' => 'date',
            'name' => $name,
            'id' => $idAttribute,
            'value' => $value,
        ];
        return new InputField($label, $attributes);
    }

    public static function unsetSessionFormErrors()
    {
        if (isset($_SESSION[self::SESSION_ERRORS_KEY])) {
            unset($_SESSION[self::SESSION_ERRORS_KEY]);
        }
    }

    public static function getBoolForCheckboxField(?string $checkboxFieldInput): bool
    {
        return $checkboxFieldInput == 'on';
    }
}
