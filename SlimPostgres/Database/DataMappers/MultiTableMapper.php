<?php
declare(strict_types=1);

namespace SlimPostgres\Database\DataMappers;

use SlimPostgres\Utilities\Functions;

abstract class MultiTableMapper implements TableMappers
{
    protected $primaryTableMapper;
    protected $selectColumns;

    protected function __construct(TableMapper $primaryTableMapper, array $selectColumns)
    {
        $this->primaryTableMapper = $primaryTableMapper;
        $this->selectColumns = $selectColumns;
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

    public function getPrimaryTableMapper(): TableMapper
    {
        return $this->primaryTableMapper;
    }

    public function getPrimaryTableName(bool $plural = true): string
    {
        return $this->primaryTableMapper->getFormalTableName($plural);
    }

    public function getTableName(bool $plural = true): string
    {
        return $this->getPrimaryTableName($plural);
    }

    public function getFormalTableName(bool $plural = true): string
    {
        return $this->getPrimaryTableName($plural);
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->primaryTableMapper->getPrimaryKeyColumnName();
    }

    public function getOrderByColumnName(): ?string
    {
        return $this->primaryTableMapper->getOrderByColumnName();
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
