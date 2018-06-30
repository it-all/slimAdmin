<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\Database\DataMappers\TableMapper;

// note that level 1 is the greatest permission
class RolesValidator
{

    private $valitron;

    function __construct(ValitronValidatorExtension $valitron)
    {
        $this->valitron = $valitron;

        $this->valitron = $this->valitron->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], FormHelper::getDatabaseTableValidationFields($this->mapper));

        $this->validator->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->mapper));

        if (count($this->mapper->getUniqueColumns()) > 0) {
            $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'Already exists.');

            foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
                $this->validator->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this->validator);
            }
        }

    }
}
