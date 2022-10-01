<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\Model;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;

// sets validation rules for database table based on table's columns
class DatabaseTableUpdateFormValidator extends DatabaseTableFormValidator
{
    public function __construct(array $inputData, TableMapper $mapper, array $record)
    {
        parent::__construct($inputData, $mapper);
        parent::setRules(true, $record);
    }
}
