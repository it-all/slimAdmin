<?php
declare(strict_types=1);

namespace Entities\Roles\Model;

use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableFormValidator;

// can add custom validation rules to roles 
class RolesValidator extends DatabaseTableFormValidator
{
    public function __construct(array $inputData, string $databaseAction = 'insert', array $record = null)
    {
        if ($databaseAction != 'insert' && $databaseAction != 'update') {
            throw new \InvalidArgumentException("databaseAction must be insert or update: $databaseAction");
        }
        if ($databaseAction == 'insert' && $record !== null) {
            throw new \InvalidArgumentException("insert action must not have record");
        }

        parent::__construct($inputData, RolesTableMapper::getInstance());

        if ($databaseAction == 'update') {
            $skipUniqueForUnchanged = true;
        } else {
            $skipUniqueForUnchanged = false;
            $record = []; // override even if entered
        }

        parent::setRules($skipUniqueForUnchanged, $record);

        // add any custom rules below 
    }
}
