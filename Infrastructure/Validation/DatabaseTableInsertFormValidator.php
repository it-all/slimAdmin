<?php
declare(strict_types=1);

namespace Infrastructure\Validation;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseMVC\View\Forms\FormHelper;

// sets validation rules for database table based on table's columns
class DatabaseTableInsertFormValidator extends DatabaseTableFormValidator
{
    public function __construct(array $inputData, TableMapper $mapper)
    {
        parent::__construct($inputData, $mapper);
        parent::setRules();
    }
}
