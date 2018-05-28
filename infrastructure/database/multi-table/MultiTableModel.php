<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Database\Multi_Table;

use It_All\Slim_Postgres\Infrastructure\Database\Single_Table\DatabaseColumnModel;
use It_All\Slim_Postgres\Infrastructure\Database\Single_Table\SingleTableModel;
use It_All\Slim_Postgres\Infrastructure\Database\TableModel;

abstract class MultiTableModel implements TableModel
{
    protected $primaryTableModel;
    protected $selectColumns;

    protected function __construct(SingleTableModel $primaryTableModel, array $selectColumns)
    {
        $this->primaryTableModel = $primaryTableModel;
        $this->selectColumns = $selectColumns;
    }

    // make sure each columnNameSql in columns
    protected function validateFilterColumns(array $filterColumnsInfo)
    {
        foreach ($filterColumnsInfo as $columnNameSql => $columnWhereInfo) {
            if (!in_array($columnNameSql, $this->selectColumns)) {
                throw new \Exception("Invalid where column $columnNameSql");
            }
        }
    }

    public function getPrimaryTableModel(): SingleTableModel
    {
        return $this->primaryTableModel;
    }

    public function getPrimaryTableName(bool $plural = true): string
    {
        return $this->primaryTableModel->getFormalTableName($plural);
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
        return $this->primaryTableModel->getPrimaryKeyColumnName();
    }

    public function getOrderByColumnName(): ?string
    {
        return $this->primaryTableModel->getOrderByColumnName();
    }

    public function getOrderByAsc(): bool
    {
        return $this->primaryTableModel->getOrderByAsc();
    }

    public function getColumnByName(string $columnName): ?DatabaseColumnModel
    {
        return $this->primaryTableModel->getColumnByName($columnName);
    }

    abstract public function select(array $filterColumnsInfo = null);

    public function getCountSelectColumns(): int
    {
        return count($this->selectColumns);
    }
}
