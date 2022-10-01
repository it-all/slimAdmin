<?php
declare(strict_types=1);

namespace Infrastructure\Database\Queries;

// helpful when adding many where clauses to a select
class SelectBuilder extends QueryBuilder
{
    private $argCounter;
    private $whereStarted; // bool in order to know whether to insert "WHERE" at the start of the where clause or "AND" 

    /*
     * $whereExtraClause without WHERE or AND but with any ()
     * $whereExtraClause cannot use parameters
     */
    function __construct(string $selectClause, string $fromClause, ?array $whereColumnsInfo = null, ?string $orderBy = null, ?int $limit = null, ?string $whereExtraClause = null)
    {
        $this->whereStarted = false;
        parent::__construct("$selectClause $fromClause");
        if ($whereColumnsInfo != null) {
            $this->argCounter = 1;
            $this->addWhereClause($whereColumnsInfo);
        }
        if ($whereExtraClause !== null) {
            $this->addWhereExtraClause($whereExtraClause);
        }
        if ($orderBy != null) {
            $this->add(" ORDER BY $orderBy");
        }
        if ($limit != null) {
            $this->add(" LIMIT $limit");
        }
    }

    private function addWhereExtraClause(string $clause)
    {
        $start = $this->whereStarted ? " AND " : " WHERE ";
        $this->add($start . $clause);
    }

    // $whereColumnsInfo [column name sql => ['operators' => [], 'values' => []] ]
    // working example array: ['status_web' => ['operators' => ['='], 'values' => ['inactive']] ]
    private function addWhereClause(array $whereColumnsInfo)
    {
        foreach ($whereColumnsInfo as $columnNameSql => $columnWhereInfoArray) {

            $operators = $columnWhereInfoArray['operators'];
            $values = $columnWhereInfoArray['values'];

            if (!isset($operators)) {
                throw new \Exception('operators key not set');
            }
            if (!is_array($operators)) {
                throw new \Exception('operators key must be array');
            }
            if (!isset($values)) {
                throw new \Exception('values key not set');
            }
            if (!is_array($values)) {
                throw new \Exception('values key must be array');
            }
            if (count($operators) != count($values)) {
                throw new \Exception("number of operators must equal number of values");
            }
            for ($i = 0; $i < count($operators); ++$i) {
                if (!parent::validateWhereOperator($operators[$i])) {
                    throw new \Exception("invalid whereOperator key ".$operators[$i]."  in whereColumnsInfo for $columnNameSql");
                }

                $this->addWhereColumn($columnNameSql, $operators[$i], $values[$i]);
            }
        }
    }

    private function addWhereColumn(string $columnNameSql, string $whereOperator, $whereValue)
    {
        $whereOperator = strtoupper($whereOperator);
        $sql = $this->whereStarted ? " AND " : " WHERE ";
        $sql .= "$columnNameSql $whereOperator ";
        if ($whereValue === null) {
            if ($whereOperator != 'IS' && $whereOperator != 'IS NOT') {
                throw new \Exception("Invalid where operator $whereOperator for null where value");
            }
            $sql .= "NULL";
            $this->add($sql);
        } else {
            $sql .= "$$this->argCounter";
            $this->add($sql, $whereValue);
            $this->argCounter++;
        }
        $this->whereStarted = true;
    }
}
