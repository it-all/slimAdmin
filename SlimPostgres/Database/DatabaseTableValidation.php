<?php
declare(strict_types=1);

namespace SlimPostgres\Database;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\DataMappers\ColumnMapper;

class DatabaseTableValidation
{
    private $tableMapper;
    private $validationColumns;

    public function __construct(TableMapper $tableMapper)
    {
        $this->tableMapper = $tableMapper;
        $this->setValidationRules();
    }

    private function setValidationRules(): array
    {
        $validation = [];
        foreach ($this->tableMapper->getColumns() as $column) {
            // primary key does not appear in forms therefore does not have validation
            // do not impose required validation on boolean (checkbox) fields, even though the column may be not null, allowing 'f' (unchecked) is fine but doing required validation will cause an error for unchecked condition

            if (!$column->isPrimaryKey() && !$column->isBoolean()) {
                $this->validationRules[$column->getName()] = self::getDatabaseColumnValidation($column);
            } 
        }

        return $validation;
    }

    public function getValidationRules(): array 
    {
        return $this->validationRules;
    }

    // just the keys (column names) of the rules array
    public function getValidationColumnNames(): array
    {
        return array_keys($this->validationRules);
    }

    public static function getDatabaseColumnValidationValue(ColumnMapper $databaseColumnMapper, string $validationType)
    {
        foreach (self::getDatabaseColumnValidation($databaseColumnMapper) as $validation) {
            if (!is_array($validation) && $validation == $validationType) {
                return true;
            } elseif (is_array($validation) && $validation[0] == $validationType) {
                return $validation[1];
            }
        }

        return false;
    }

    public static function getDatabaseColumnValidation(ColumnMapper $databaseColumnMapper): array
    {
        $columnValidation = [];
        $columnConstraints = $databaseColumnMapper->getConstraints();

        if (!$databaseColumnMapper->getIsNullable()) {
            $columnValidation[] = 'required';
        }

        if ($databaseColumnMapper->getCharacterMaximumLength() != null) {
            $columnValidation[] = ['lengthMax', $databaseColumnMapper->getCharacterMaximumLength()];
        }

        if ($databaseColumnMapper->isNumericType()) {
            if ($databaseColumnMapper->isIntegerType()) {
                $columnValidation[] = 'integer';
                switch ($databaseColumnMapper->getType()) {
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

}
