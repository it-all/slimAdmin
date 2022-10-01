<?php
declare(strict_types=1);

namespace Infrastructure\Database\Queries;

/**
 * This receives ? as placeholders for variables in the $valuesSql arg and converts them to 
 * $1, $2, ...
 * 
 * Ex:
 *
 *     $ins = new MultiRowInserter("insert into foo (a, b, c) values", "(?, ?, now())", 20);
 *     foreach ($rows as $row) {
 *         $ins->add([ $row->a, $row->b ]);
 *     }
 *     $ins->execute();
 *
 */
class MultiRowInserter 
{
    private $baseSql;
    private $valuesSql;
    private $executeEvery;
    private $args;
    private $countRows;

    function __construct($baseSql, $valuesSql, $executeEvery=10) 
    {
        $this->baseSql = $baseSql;
        $this->valuesSql = $valuesSql;
        $this->executeEvery = $executeEvery;
        $this->clear();
    }

    function add($row) 
    {
        $this->args = array_merge($this->args, $row);
        $this->countRows++;
        if ($this->countRows >= $this->executeEvery) {
            $this->execute();
        }
    }

    function getRowCount() 
    { 
        return $this->countRows; 
    }

    /** returns array of the values in valuesSql property */
    private function getValuesSqlValues(): array 
    {
        /** substr to remove the opening and closing parens */
        return explode(",", substr($this->valuesSql, 1, strlen($this->valuesSql) - 2));
    }
    /** convert (?, ?, ?, ?) into ~ ($1, $2, $3, $4) */
    private function convertValuesSql(int $varCount = 1): string 
    {
        $newValParts = [];
        foreach ($this->getValuesSqlValues() as $valPart) {
            if (trim($valPart) == "?") {
                $newValParts[] = "$" . $varCount;
                $varCount++;
            } else {
                $newValParts[] = $valPart;
            }
        }
        $newValuesSql = "(" . implode(",", $newValParts). ")";
        return $newValuesSql;
    }

    private function getNumVarsInValuesSql(): int 
    {
        $varCount = 0;
        foreach ($this->getValuesSqlValues() as $valPart) {
            if (trim($valPart) == "?") {
                $varCount++;
            } 
        }
        return $varCount;
    }

    /** convert (?, ?, ?, ?) into ($1, $2, $3, $4), ($5, $6, $7, $8), ... */
    private function getAllRowExprs(): string 
    {
        $allRowExprs = "";
        $numVars = $this->getNumVarsInValuesSql(); /** the number of variables in the initial property */
        for ($i = 1; $i <= $this->countRows; ++$i) {
            $varCount = ($i - 1) * $numVars + 1;
            if ($i > 1) {
                $allRowExprs .= ", ";
            }
            $allRowExprs .= $this->convertValuesSql($varCount);
        }
        return $allRowExprs;
    }

    function execute() 
    {
        if ($this->countRows == 0) {
            return;
        }

        $finalSql = $this->baseSql . ' ' . $this->getAllRowExprs();
        $q = new QueryBuilder();
        $q->set($finalSql, $this->args);
        $q->execute();

        $this->clear();
    }

    private function clear() 
    {
        $this->countRows = 0;
        $this->args = [];
    }
}
