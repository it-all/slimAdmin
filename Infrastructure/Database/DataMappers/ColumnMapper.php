<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

use Infrastructure\Database\Postgres;
use Infrastructure\Database\Queries\QueryBuilder;

class ColumnMapper
{
    /** @var  */
    private $tableMapper;

    /** @var string */
    private $name;

    /** @var string postgres column type */
    private $type;

    /** @var bool */
    private $isNullable;

    /**
    * @var bool
    * true if column has a unique constraint
    */
    private $isUnique;

    private $constraints;

    /** @var ?string */
    private $defaultValue;

    /** @var bool if default is nextval() ie a sequence */
    private $hasNextVal;

    /** @var string|null (if does not apply) */
    private $characterMaximumLength;

    /** @var string */
    private $udtName;

    /** @var  array only applies to enum (USER-DEFINED) types */
    private $enumOptions;

    /** @var bool */
    private $isNextVal;

    function __construct(TableMapper $tableMapper,  array $columnInfo)
    {
        $this->constraints = []; // initialize
        $this->enumOptions = []; // initialize
        $this->tableMapper = $tableMapper;
        $this->name = $columnInfo['column_name'];
        $this->type = $columnInfo['data_type'];
        $this->isNullable = $columnInfo['is_nullable'] == 'YES';
        if ($this->type == 'boolean' && $this->isNullable) {
            // alert of database design problem
            throw new \Exception("Column {$this->name} of table {$this->tableMapper->getTableName()} is boolean and nullable, that's a no-no");
        }
        $this->udtName = $columnInfo['udt_name'];
        $this->isUnique = $columnInfo['is_unique'];
        if ($this->isUnique) {
            $this->addConstraint('unique');
        }
        $this->setEnumOptions();
        $this->setDefaultValue($columnInfo['column_default']);
        $this->setIsNextVal();
        $this->characterMaximumLength = $columnInfo['character_maximum_length'];
    }

    // make public since ORM does not sniff out every constraint, some must be added manually when table mapper is extended
    // context can be bool or particular string/value related to constraint
    public function addConstraint(string $constraint, $context = true)
    {
        $this->constraints[$constraint] = $context;
    }

    /** input can be null */
    private function setDefaultValue(?string $columnDefault)
    {
        if (is_null($columnDefault)) {
            $this->defaultValue = ''; // empty string if there is no default
        } else {
            switch ($this->type) {
                case 'character':
                case 'character varying':
                case 'text':
                    // formatted like 'default'::text
                case 'USER-DEFINED':
                    // formatted like 'default'::tableName_columnName
                    // parse out default
                    $parseColumnDefault = explode("::", $columnDefault);
                    $this->defaultValue = $parseColumnDefault[0] === "NULL" ? null : str_replace("'", "", $parseColumnDefault[0]); // null for null default
                    
                    break;
                case 'boolean':
                    // overwrite to be congruous with a postgres value returned from select
                    if ($columnDefault == 'true') {
                        $this->defaultValue = Postgres::BOOLEAN_TRUE;
                    }
                    break;
                default:
                    $this->defaultValue = $columnDefault;
            }
        }
    }

    private function setIsNextVal() 
    {
        $this->isNextVal = $this->defaultValue != null && 'nextval' == substr($this->defaultValue, 0, 7);
    }

    public function setEnumOptions()
    {
        if ($this->type == 'USER-DEFINED') {
            $q = new QueryBuilder("SELECT e.enumlabel as enum_value FROM pg_type t JOIN pg_enum e on t.oid = e.enumtypid JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace WHERE t.typname = $1", $this->udtName);
            $qResult = $q->execute();
            if (pg_num_rows($qResult) == 0) {
                throw new \Exception("No values for enum field $this->name");
            }
            while ($row = pg_fetch_assoc($qResult)) {
                $this->enumOptions[] = $row['enum_value'];
            }
        }
    }

    /**
     * @return bool
     * not to be confused with column type numeric, it can be any type in $this->numericTypes
     */
    public function isNumericType(): bool
    {
        return in_array($this->type, Postgres::NUMERIC_TYPES);
    }

    /**
     * @return bool
     */
    public function isIntegerType(): bool
    {
        return in_array($this->type, Postgres::INTEGER_TYPES);
    }

    public function isPrimaryKey()
    {
        return $this->name == $this->tableMapper->getPrimaryKeyColumnName();
    }

    public function isBoolean(): bool
    {
        return ($this->type == 'boolean');
    }

    // getters

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIsNullable(): bool
    {
        return $this->isNullable;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getIsUnique(): bool
    {
        return $this->getConstraint('unique');
    }

    public function getCharacterMaximumLength()
    {
        return $this->characterMaximumLength;
    }

    public function getUdtName(): string
    {
        return $this->udtName;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function getIsNextVal(): bool
    {
        return $this->isNextVal;
    }

    public function getEnumOptions(): array
    {
        return $this->enumOptions;
    }

    public function getConstraint(string $constraint): bool
    {
        return in_array($constraint, $this->constraints);
    }

    public function recordExistsForValue($value): bool
    {
        $q = new QueryBuilder("SELECT ".$this->tableMapper->getPrimaryKeyColumnName()." FROM ".$this->tableMapper->getTableName()." WHERE $this->name = $1", $value);
        return null !== $q->getRow();
    }
}
