<?php
declare(strict_types=1);

namespace Infrastructure\Validation;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseMVC\View\Forms\FormHelper;

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

        $uniqueColumns = $this->mapper->getUniqueColumns();

        if (count($uniqueColumns) > 0) {
            $this->addUniqueRule();

            foreach ($uniqueColumns as $databaseColumnMapper) {
                $field = $databaseColumnMapper->getName();
                // only set rule for changed columns if $skipUniqueForUnchanged is set true
                if ( !($skipUniqueForUnchanged && $this->inputData[$field] == $record[$field]) ) {
                    $this->rule('unique', $field, $databaseColumnMapper, $this);
                }
            }
        }
    }
 }
