<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

use Infrastructure\Database\DataMappers\ListViewMappers;
use Infrastructure\Database\Postgres;
use Infrastructure\Database\Queries\InsertBuilder;
use Infrastructure\Database\Queries\InsertUpdateBuilder;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Database\Queries\UpdateBuilder;
use Exceptions;

class TableMapper implements ListViewMappers
{
    /** @var string  */
    protected $tableName;

    /** @var  array of column mapper objects */
    protected $columns;

    /** @var array of columnNames */
    protected $columnNames;

    /** @var string or null if no primary key column exists */
    protected $primaryKeyColumnName;

    protected $orderByColumnName;

    /** @var bool  */
    protected $orderByAsc;

    /**
     * @var array of columnNames with UNIQUE constraint or index
     * NOTE this does not handle multi-column unique constraints
     */
    private $uniqueColumnNames;

    private $uniqueColumns;

    protected $defaultSelectColumnsString;

    protected $pgConnection;

    public function __construct(string $tableName, $defaultSelectColumnsString = "*", ?string $orderByColumnName = null, bool $orderByAsc = true)
    {
        $this->pgConnection = (Postgres::getInstance())->getConnection();
        $this->tableName = $tableName;
        $this->primaryKeyColumnName = null; // initialize

        $this->uniqueColumns = [];
        $this->uniqueColumnNames = [];

        // $this->primaryKeyColumnName will be updated if exists
        // $this->uniqueColumnNames added (then used to set $column->isUnique
        $this->setConstraints();

        $this->defaultSelectColumnsString = $defaultSelectColumnsString;

        if ($orderByColumnName != null) {
            $this->orderByColumnName = $orderByColumnName;
        } elseif ($this->primaryKeyColumnName != null) {
            $this->orderByColumnName = $this->primaryKeyColumnName;
        } else {
            $this->orderByColumnName = null;
        }
        $this->orderByAsc = $orderByAsc;

        // $this->uniqueColumns added
        $this->setColumns();
    }

    /** note this will set uniqueColumnNames whether they are set as a constraint or an index */
    private function setConstraints()
    {
        $q = new QueryBuilder("SELECT ccu.column_name, tc.constraint_type FROM INFORMATION_SCHEMA.constraint_column_usage ccu JOIN information_schema.table_constraints tc ON ccu.constraint_name = tc.constraint_name WHERE tc.table_name = ccu.table_name AND ccu.table_name = $1", $this->tableName);
        $qResult = $q->execute();
        while ($qRow = pg_fetch_assoc($qResult)) {
            switch($qRow['constraint_type']) {
                case 'PRIMARY KEY':
                    $this->primaryKeyColumnName = $qRow['column_name'];
                    break;
                case 'UNIQUE':
                    $this->uniqueColumnNames[] = $qRow['column_name'];
            }
        }
    }

    protected function setColumns()
    {
        try {
            $rs = Postgres::getTableMetaData($this->tableName);
        } catch (\Exception $e) {
            throw new \Exception("Unable to set columns for table $this->tableName");
        }

        $this->columnNames = [];

        while ($columnInfo = pg_fetch_assoc($rs)) {
            $columnInfo['is_unique'] = in_array($columnInfo['column_name'], $this->uniqueColumnNames);
            $c = new ColumnMapper($this, $columnInfo);
            $this->columns[] = $c;
            $this->columnNames[] = $columnInfo['column_name'];
            if ($columnInfo['is_unique']) {
                $this->uniqueColumns[] = $c;
            }
        }
    }

    public function getListViewTitle(): string 
    {
        return $this->getFormalTableName();
    }

    public function getInsertTitle(): string
    {
        return "Insert ".$this->getFormalTableName();
    }

    // make protected since ORM does not sniff out every constraint, some must be added manually when table mapper is extended
    protected function addColumnConstraint(ColumnMapper $column, string $constraint, $context = true)
    {
        $column->addConstraint($constraint, $context);
    }

    protected function addColumnNameConstraint(string $columName, string $constraint)
    {
        $this->addColumnConstraint($this->getColumnByName($columName), $constraint);
    }

    public function getColumnConstraints(): array
    {
        $constraints = [];
        foreach($this->columns as $column) {
            $constraints[$column->getName()] = $column->getConstraints();
        }
        return $constraints;
    }

    public function getDefaultSelectColumnsString(): string 
    {
        return $this->defaultSelectColumnsString;
    }

    /** returns either array of rows or null 
     *  $whereColumnsInfo [column name sql => ['operators' => [], 'values' => []] ]
     *  working example array: ['status_web' => ['operators' => ['='], 'values' => ['inactive']] ]
     */
    public function select(?string $columns = null, ?array $whereColumnsInfo = null, ?string $orderBy = null, ?int $limit = null): ?array
    {
        $columns = $columns ?? $this->defaultSelectColumnsString;
        $orderBy = $orderBy ?? $this->getOrderBy();
        $q = new SelectBuilder("SELECT $columns", "FROM $this->tableName", $whereColumnsInfo, $orderBy, $limit);
        return $q->executeGetArray();
    }

    protected function getOrderBy(): ?string
    {
        if ($this->orderByColumnName != null) {
            $orderByString = "$this->orderByColumnName";
            if (!$this->orderByAsc) {
                $orderByString .= " DESC";
            }
            return $orderByString;
        }
        return null;
    }

    public function hasColumnValue(ColumnMapper $databaseColumnMapper, $value): bool
    {
        $q = new QueryBuilder("SELECT ".$this->getPrimaryKeyColumnName()." FROM $this->tableName WHERE ".$databaseColumnMapper->getName()." = $1", [$value]);
        if (null === $row = $q->getRow()) {
            return false;
        }
        return (bool) $row[0];

    }

    public function getRowCount(?string $columnName = null, ?array $whereColumnsInfo = null): ?int
    {
        // Should this logic be a function?
        if ($columnName === null) {
            if ($this->primaryKeyColumnName === null) { // case where there is no primary key
                $columnName = '*'; // Fail safe
            } else {
                $columnName = $this->primaryKeyColumnName;
            }
        }

        $q = new SelectBuilder("SELECT COUNT($columnName)", "FROM $this->tableName", $whereColumnsInfo);
        if (null === $row = $q->getRow()) {
            return null; // Don't typecast null result to ensure null is returned
        }
        return (int) $row[0];
    }

    /** unlike select(), this returns only the array for a single record, or null */
    public function selectForPrimaryKey($primaryKeyValue, string $columns = "*"): ?array
    {
        if (is_null($this->getPrimaryKeyColumnName())) {
            throw new \Exception("$this->tableName primary key not defined");
        }
        $where = [
            $this->getPrimaryKeyColumnName() => [
                'operators' => ['='],
                'values' => [$primaryKeyValue],
            ],
        ];
        if (null !== $records = $this->select($columns, $where)) {
            return $records[0];
        }
        return null;
    }

    public function getBooleanColumnNames(): array
    {
        $booleanFieldNames = [];
        foreach ($this->columns as $column) {
            if ($column->isBoolean()) {
                $booleanFieldNames[] = $column->getName();
            }
        }
        return $booleanFieldNames;
    }

    public function getNonNullableBooleanColumns(): ?array 
    {
        $columns = [];
        foreach ($this->columns as $column) {
            if ($column->isBoolean() && !$column->getIsNullable()) {
                $columns[] = $column;
            }
        }
        return !$columns ? null : $columns;
    }

    /** adds columnName => 'f' into columnValues array for any non-nullable boolean column in the table that does not exist in columnValues */
    private function addBooleanColumnFalse(array $columnValues): array
    {
        if (null !== $nonNullableBoolColumns = $this->getNonNullableBooleanColumns()) {
            foreach ($nonNullableBoolColumns as $nonNullableBoolColumn) {
                $columnName = $nonNullableBoolColumn->getName();
                if (!isset($columnValues[$columnName])) {
                    $columnValues[$columnName] = Postgres::BOOLEAN_FALSE;
                }
            }
        }
        // foreach ($this->getBooleanColumnNames() as $booleanColumnName) {
        //     if (!isset($columnValues[$booleanColumnName])) {
        //         $columnValues[$booleanColumnName] = Postgres::BOOLEAN_FALSE;
        //     }
        // }

        return $columnValues;
    }

    /**
     *  find all boolean columns
     *  if not in inputValues (and addNew true) or null or false add as postgres false
     *  otherwise convert to postgres true
     *  note that boolean columns should not be nullable, so ok to set false
     *  see https://thoughtbot.com/blog/avoid-the-threestate-boolean-problem
     */
    private function booleanValues(array $inputValues, bool $addNew = true): array 
    {
        $falseValues = [null, false, 0, "off", "f"];
        $valuesPlusBools = $inputValues;
        foreach ($this->getBooleanColumnNames() as $booleanFieldName) {
            if (!array_key_exists($booleanFieldName, $inputValues)) {
                if ($addNew) {
                    $valuesPlusBools[$booleanFieldName] = Postgres::BOOLEAN_FALSE;
                }
            } else {
                $valuesPlusBools[$booleanFieldName] = in_array($inputValues[$booleanFieldName], $falseValues, true) ? Postgres::BOOLEAN_FALSE : Postgres::BOOLEAN_TRUE;
            }
        }

        return $valuesPlusBools;
    }

    /** returns primary key if set, otherwise returns pg result */
    public function insert(array $insertData)
    {
        $columnDataIncludingBools = $this->booleanValues($insertData);
        $ib = new InsertBuilder($this->tableName);
        if ($this->getPrimaryKeyColumnName() !== null) {
            $ib->setPrimaryKeyName($this->getPrimaryKeyColumnName());
        }
        $this->addColumnsToBuilder($ib, $columnDataIncludingBools);
        return $ib->runExecute();
    }

    public function getChangedColumnsValues(array $inputValues, array $record): array
    {
        $changedColumns = [];
        foreach ($inputValues as $columnName => $value) {
            // throw out any new values that are not table columns
            if ($this->getColumnByName($columnName) !== null && $value != $record[$columnName]) {
                $changedColumns[$columnName] = $value;
            }
        }
        return $changedColumns;
    }

    /**
     * @param array $columnValues
     * @param $primaryKeyValue
     * @param bool $getChangedValues :: default true. if true calls getChangedColumnsValues in order to send only changed to update builder, otherwise all $columnValues is sent to update builder. set false if input only includes changed values in order to not duplicate checking for changes.
     * @param array $record :: best to include if $getChangedValues is true in order to not duplicate select query
     * @param bool $addNewBools if a boolean column does not exist in input Values it will be added as false - do not need if only sending changed values as input
     */
    public function updateByPrimaryKey(array $inputValues, $primaryKeyValue, bool $getChangedValues = true, array $record = null, bool $addNewBools = false)
    {
        if (is_null($primaryKeyValue)) {
            throw new \InvalidArgumentException("primary key value cannot be null");
        }
        
        $columnValuesBools = $this->booleanValues($inputValues, $addNewBools);

        if ($getChangedValues) {
            if ($record == null) {
                if (null === $record = $this->selectForPrimaryKey($primaryKeyValue)) {
                    throw new Exceptions\QueryResultsNotFoundException("No record for primary key $primaryKeyValue");
                }
            }
            $updateColumnValues = $this->getChangedColumnsValues($columnValuesBools, $record);
        } else {
            $updateColumnValues = $columnValuesBools;
        }

        if (count($updateColumnValues) == 0) {
            throw new Exceptions\QueryUpdateNoChangesException("No changed columns");
        }

        $ub = new UpdateBuilder($this->tableName, $this->getPrimaryKeyColumnName(), $primaryKeyValue);
        $this->addColumnsToBuilder($ub, $updateColumnValues);
        $dbResult = $ub->runExecute();
        
        if (pg_affected_rows($dbResult) == 0) {
            throw new Exceptions\QueryResultsNotFoundException();
        }
    }

    /** 
     * if row is not deleted (ie primary key value not found), an exception is thrown
     * note that $primaryKeyValue argument is untyped as it can be string or int 
    */
    public function deleteByPrimaryKey($primaryKeyValue, ?string $returnField = null): ?string
    {
        $query = "DELETE FROM $this->tableName WHERE ".$this->getPrimaryKeyColumnName()." = $1";
        $q = new QueryBuilder($query, $primaryKeyValue);

        if ($returnField !== null) {
            return $q->executeWithReturnField($returnField);
        }

        $dbResult = $q->execute();

        if (pg_affected_rows($dbResult) == 0) {
            throw new Exceptions\QueryResultsNotFoundException();
        }

        return null;
    }

    private function addColumnsToBuilder(InsertUpdateBuilder $builder, array $columnValues)
    {
        foreach ($columnValues as $name => $value) {
            /** make sure this is truly a column */
            if (null !== $column = $this->getColumnByName($name)) {
                if (is_string($value) && mb_strlen($value) == 0) {
                    $value = $this->handleBlankValue($column);
                }
                $builder->addColumn($name, $value);
            } else {
                throw new \InvalidArgumentException("Database table ".$this->tableName." does not contain a $name column");
            }
        }
    }

    private function handleBlankValue(ColumnMapper $column)
    {
        // set to null if field is nullable
        if ($column->getIsNullable()) {
            return null;
        }

        // set to 0 if field is numeric
        if ($column->isNumericType()) {
            return 0;
        }

        // set to f if field is boolean
        if ($column->isBoolean()) {
            return Postgres::BOOLEAN_FALSE;
        }

        return '';
    }

    // getters

    /** replaces _ with space */
    public function getFormalTableName(): string
    {
        $name = ucwords(str_replace('_', ' ', $this->tableName));
        return $name;
    }

    /** removes last char */
    public function getTableNameSingular(): string
    {
        return substr($this->tableName, 0, strlen($this->tableName) - 1);
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getPrimaryKeyColumnName(): ?string
    {
        return $this->primaryKeyColumnName;
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->getPrimaryKeyColumnName();
    }

    public function getOrderByColumnName(): ?string
    {
        return $this->orderByColumnName;
    }

    public function getListViewSortColumn(): ?string 
    {
        return $this->getOrderByColumnName();
    }

    public function getListViewSortAscending(): bool 
    {
        return $this->getOrderByAsc();
    }
    
    public function getOrderByAsc(): bool
    {
        return $this->orderByAsc;
    }

    public function getColumns(): array
    {
        if (count($this->columns) == 0) {
            throw new \Exception('No columns in table '.$this->tableName);
        }
        return $this->columns;
    }

    public function getColumnNames(): array 
    {
        return $this->columnNames;
    }

    public function getColumnByName(string $columnName): ?ColumnMapper
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $columnName) {
                return $column;
            }
        }

        return null;
    }

    public function getUniqueColumnNames(): array
    {
        return $this->uniqueColumnNames;
    }

    public function getUniqueColumns(): array
    {
        return $this->uniqueColumns;
    }

    public function getCountSelectColumns(): int
    {
        if ($this->defaultSelectColumnsString == '*') {
            return count($this->columns);
        }

        return count(explode(",", $this->defaultSelectColumnsString));
    }
}
