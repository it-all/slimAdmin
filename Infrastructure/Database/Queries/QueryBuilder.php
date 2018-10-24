<?php
declare(strict_types=1);

namespace Infrastructure\Database\Queries;

use Infrastructure\Database\Postgres;
use Exceptions\QueryFailureException;
use Exceptions\QueryResultsNotFoundException;

class QueryBuilder
{
    protected $sql;
    protected $args = array();
    const OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'IS', 'IS NOT', 'LIKE', 'ILIKE'];

    /**
     * QueryBuilder constructor. like add, for convenience
     */
    function __construct()
    {
        $args = func_get_args();
        // note func_num_args returns 0 if just 1 argument of null passed in
        if (count($args) > 0) {
            call_user_func_array(array($this, 'add'), $args);
        }
    }

    /**
     * appends sql and args to query
     * @param string $sql
     * @return $this
     */
    public function add(string $sql)
    {
        $args = func_get_args();
        array_shift($args); // drop the first one (the sql string)
        $this->sql .= $sql;
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    /**
     * handle null argument for correct sql
     * @param string $name
     * @param $arg
     * @return $this
     */
    public function null_eq(string $name, $arg)
    {
        if ($arg === null) {
            $this->sql .= "$name is null";
        }
        else {
            $this->args[] = $arg;
            $argNum = count($this->args);
            $this->sql .= "$name = \$$argNum";
        }
        return $this;
    }

    /**
     * sets sql and args
     * @param string sql
     * @param $args
     */
    public function set(string $sql, array $args)
    {
        $this->sql = $sql;
        $this->args = $args;
    }

    private function alterBooleanArgs()
    {
        foreach ($this->args as $argIndex => $arg) {
            if (is_bool($arg)) {
                $this->args[$argIndex] = ($arg) ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE;
                $this->args[$argIndex] = self::convertBoolToPostgresBool($arg);
            }
        }
    }

    public function execute(bool $alterBooleanArgs = false)
    {
        if ($alterBooleanArgs) {
            $this->alterBooleanArgs();
        }

        $postgresConnection = (Postgres::getInstance())->getConnection();

        /** query failures within transactions without suppressing errors for pg_query_params caused two errors, only 1 of which was inserted to the database log */
        if (!$result = @pg_query_params($postgresConnection, $this->sql, $this->args)) {
            /** note pg_last_error seems to often not return anything, but pg_query_params call will result in php warning */
            $msg = pg_last_error($postgresConnection) . " " . $this->sql;
            if (count($this->args) > 0) {
                $msg .= PHP_EOL . " Args: " . var_export($this->args, true);
            }

            throw new QueryFailureException($msg, E_ERROR);
        }

        $this->resetQuery(); /** prevent accidental multiple execution */
        return $result;
    }

    /**
     * In order to receive a column value back for INSERT, UPDATE, and DELETE queries
     * Note that RETURNING can include multiple fields or expressions in SQL, but this only accepts one field. To receive multiple, simply call execute() instead and process the returned result similar to below
     * Note also that if an invalid $returnField is received, the query still executes prior to throwing the InvalidArgumentException.
     */
    public function executeWithReturnField(string $returnField, bool $alterBooleanArgs = false)
    {
        $this->add(" RETURNING $returnField");

        /** note, if query fails exception thrown in execute */
        $result = $this->execute($alterBooleanArgs);

        if (pg_num_rows($result) > 0) {
            $returned = pg_fetch_all($result);
            if (!isset($returned[0][$returnField])) {
                throw new \InvalidArgumentException("Query executed, but $returnField column does not exist");
            }
            return $returned[0][$returnField];
        } else {
            /** nothing was found - ie an update or delete that found no matches to the WHERE clause */
            throw new QueryResultsNotFoundException();
        }
    }

    /**
     * returns the value of the one column in one record or null if 0 records result
     */
    public function getOne()
    {
        $result = $this->execute();
        if (pg_num_rows($result) == 1) {
            // make sure only 1 field in query
            if (pg_num_fields($result) == 1) {
                return pg_fetch_array($result)[0];
            }
            else {
                throw new \Exception("Too many result fields");
            }
        }
        else {
            // either 0 or multiple records in result
            // if 0
            if (pg_num_rows($result) == 0) {
                // no error here. client can error if appropriate
                return null;
            }
            else {
                throw new \Exception("Multiple results");
            }
        }
    }

    public static function validateWhereOperator(string $op): bool
    {
        return in_array($op, self::OPERATORS);
    }

    public static function getWhereOperatorsText(): string
    {
        $ops = "";
        $opCount = 1;
        foreach (self::OPERATORS as $op) {
            $ops .= "$op";
            if ($opCount < count(self::OPERATORS)) {
                $ops .= ", ";
            }
            $opCount++;
        }
        return $ops;
    }

    public function getSql(): ?string
    {
        if (isset($this->sql)) {
            return $this->sql;
        }

        return null;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function resetQuery()
    {
        $this->sql = '';
        $this->args = [];
    }

}
