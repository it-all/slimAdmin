<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

use Infrastructure\Database\Postgres;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Functions;

abstract class EntityMapper
{
    protected $defaultSelectColumnsString;
    protected $pgConnection;

    protected function __construct()
    {
        $this->pgConnection = (Postgres::getInstance())->getConnection();
    }

    public function setDefaultSelectColumnsString() 
    {
        $selectColumnsString = "";
        foreach (static::SELECT_COLUMNS as $name => $columnSql) {
            $selectColumnsString .= "$columnSql, ";
        }
        $this->defaultSelectColumnsString = Functions::removeLastCharsFromString($selectColumnsString, 2);
    }

    /** returns array of records or null */
    public function select(?string $columns = null, ?array $whereColumnsInfo = null, ?string $orderBy = null, ?int $limit = null): ?array
    {
        if ($whereColumnsInfo != null) {
            $this->validateWhere($whereColumnsInfo);
        }
             
        $columns = $columns ?? $this->defaultSelectColumnsString;
        $orderBy = $orderBy ?? $this->getOrderBy();
        
        $q = new SelectBuilder("SELECT $columns", $this->getFromClause(), $whereColumnsInfo, $orderBy, $limit);
        return $q->executeGetArray();
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
