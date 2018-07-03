<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Utilities\ValitronValidatorExtension;
use SlimPostgres\Forms\FormHelper;
use SlimPostgres\Database\DatabaseTableValidation;

// sets validation rules for database table based on table's columns
abstract class DatabaseTableFormValidator extends ValitronValidatorExtension
{
    private $inputData;
    private $mapper;
    private $databaseTableValidation;

    public function __construct(array $inputData, TableMapper $mapper)
    {
        $this->inputData = $inputData;
        $this->mapper = $mapper;
        $this->databaseTableValidation = new DatabaseTableValidation($this->mapper);
        parent::__construct($inputData, $this->databaseTableValidation->getValidationColumnNames());
    }

    // called by both insert and update children. update will call with skip true and record data so that only columns that have been changed have the unique rule applied, otherwise validation would fail for unchanged unique fields
    protected function setRules(bool $skipUniqueForUnchanged = false, array $record = null)
    {
        $this->mapFieldsRules($this->databaseTableValidation->getValidationRules());

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
 }
