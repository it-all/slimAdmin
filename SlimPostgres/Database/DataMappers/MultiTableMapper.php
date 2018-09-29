<?php
declare(strict_types=1);

namespace SlimPostgres\Database\DataMappers;

use SlimPostgres\Utilities\Functions;

abstract class MultiTableMapper implements TableMappers
{
    protected $primaryTableMapper;
    protected $selectColumns;
    protected $orderByColumnName;

    protected function __construct(TableMapper $primaryTableMapper, array $selectColumns, string $orderByColumnName)
    {
        $this->primaryTableMapper = $primaryTableMapper;
        $this->selectColumns = $selectColumns;
        $this->orderByColumnName = $orderByColumnName;
    }

    abstract public function select(string $columns = "*", array $filterColumnsInfo = null);

    public function getSelectColumnsString(): string 
    {
        $selectColumnsString = "";
        foreach (static::SELECT_COLUMNS as $name => $columnSql) {
            $selectColumnsString .= "$columnSql,";
        }
        return Functions::removeLastCharFromString($selectColumnsString);
    }

    // make sure each columnNameSql in columns
    protected function validateWhere(array $whereColumnsInfo)
    {
        foreach ($whereColumnsInfo as $columnNameSql => $columnWhereInfo) {
            if (!in_array($columnNameSql, $this->selectColumns)) {
                throw new \Exception("Invalid where column $columnNameSql");
            }
        }
    }
    
    protected function getSelectClause(): string 
    {
        return "SELECT " . $this->getSelectColumnsString();
    }

    public function getPrimaryTableMapper(): TableMapper
    {
        return $this->primaryTableMapper;
    }

    public function getPrimaryTableName(): string
    {
        return $this->primaryTableMapper->getTableName();
    }

    public function getTableName(): string
    {
        return $this->getPrimaryTableName();
    }

    public function getFormalTableName(bool $plural = true): string
    {
        return $this->primaryTableMapper->getFormalTableName($plural);
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->primaryTableMapper->getPrimaryKeyColumnName();
    }

    /** returns only column name */
    public function getOrderByColumnName(): string
    {
        return $this->orderByColumnName;
    }

    /** returns table.colum */
    protected function getOrderBy(): string 
    {
        return $this->selectColumns[$this->orderByColumnName];
    }

    public function getOrderByAsc(): bool
    {
        return $this->primaryTableMapper->getOrderByAsc();
    }

    public function getColumnByName(string $columnName): ?ColumnMapper
    {
        return $this->primaryTableMapper->getColumnByName($columnName);
    }

    public function getCountSelectColumns(): int
    {
        return count($this->selectColumns);
    }
}
