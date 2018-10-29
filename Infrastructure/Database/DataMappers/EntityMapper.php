<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

use Infrastructure\Database\DataMappers\ListViewMappers;
use Infrastructure\Functions;

abstract class EntityMapper implements ListViewMappers
{
    protected $defaultSelectColumnsString;

    // abstract public function getListViewTitle(): string;
    // abstract public function getInsertTitle(): string;
    // abstract public function getUpdateColumnName(): ?string;
    // abstract public function getListViewSortColumn(): ?string;
    // abstract public function getListViewSortAscending(): bool;
    // abstract protected function getFromClause();
    // abstract protected function getOrderBy();

    public function setDefaultSelectColumnsString() 
    {
        $selectColumnsString = "";
        foreach (static::SELECT_COLUMNS as $name => $columnSql) {
            $selectColumnsString .= "$columnSql, ";
        }
        $this->defaultSelectColumnsString = Functions::removeLastCharsFromString($selectColumnsString, 2);
    }

    /** returns array of records or null */
    public function select(?string $columns = "*", ?array $whereColumnsInfo = null, ?string $orderBy = null): ?array
    {
        if ($whereColumnsInfo != null) {
            $this->validateWhere($whereColumnsInfo, self::SELECT_COLUMNS);
        }
             
        $columns = $columns ?? $this->defaultSelectColumnsString;
        $orderBy = $orderBy ?? $this->getOrderBy();
        
        $q = new SelectBuilder("SELECT $columns", $this->getFromClause(), $whereColumnsInfo, $orderBy);
        $pgResult = $q->execute();
        if (!$results = pg_fetch_all($pgResult)) {
            $results = null;
        }
        pg_free_result($pgResult);
        return $results;
    }

    // make sure each columnNameSql in columns
    protected function validateWhere(array $whereColumnsInfo)
    {
        foreach ($whereColumnsInfo as $columnNameSql => $columnWhereInfo) {
            if (!in_array($columnNameSql, static::SELECT_COLUMNS)) {
                throw new \Exception("Invalid where column $columnNameSql");
            }
        }
    }

    public function getCountSelectColumns(): int
    {
        return count(static::SELECT_COLUMNS);
    }
}
