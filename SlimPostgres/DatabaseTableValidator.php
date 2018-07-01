<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Utilities\ValitronValidatorExtension;
use SlimPostgres\Forms\FormHelper;

// sets validation rules for database table based on table's columns
class DatabaseTableValidator extends ValitronValidatorExtension
{
    private $mapper;
    private $inputData;

    // sets validation rules
    public function __construct(TableMapper $mapper, array $inputData)
    {
        parent::__construct($inputData, FormHelper::getDatabaseTableValidationFields($mapper));
        $this->mapper = $mapper;
        $this->inputData = $inputData;
    }

    // common to both insert and update
    private function setRules(bool $skipUniqueForUnchanged = false, array $record = null)
    {
        $this->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->mapper));

        if (count($this->mapper->getUniqueColumns()) > 0) {
            self::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'already exists');

            foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
                // only set rule for changed columns
                if (!$skipUniqueForUnchanged || $this->inputData[$databaseColumnMapper->getName()] != $record[$databaseColumnMapper->getName()]) {
                    $this->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this);
                }
            }
        }
    }

    public function setInsertRules()
    {
        $this->setRules();

        // if (count($this->mapper->getUniqueColumns()) > 0) {

        //     foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
        //         $this->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this);
        //     }
        // }
    }

    public function setUpdateRules(array $record)
    {
        $this->setRules(true, $record);

        // if (count($this->mapper->getUniqueColumns()) > 0) {
        //     foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
        //         // only set rule for changed columns
        //         if ($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$databaseColumnMapper->getName()] != $record[$databaseColumnMapper->getName()]) {
        //             $this->validator->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this->validator);
        //         }
        //     }
        // }
    }
}
