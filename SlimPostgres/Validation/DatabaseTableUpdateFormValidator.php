<?php
declare(strict_types=1);

namespace SlimPostgres\Validation;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\BaseMVC\View\Forms\FormHelper;

// sets validation rules for database table based on table's columns
class DatabaseTableUpdateFormValidator extends DatabaseTableFormValidator
{
    public function __construct(array $inputData, TableMapper $mapper, array $record)
    {
        parent::__construct($inputData, $mapper);
        parent::setRules(true, $record);
    }
}
