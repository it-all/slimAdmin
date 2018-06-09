<?php
declare(strict_types=1);

namespace SlimPostgres\Database\Queries;

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
                $this->args[$argIndex] = ($arg) ? 't' : 'f';
            }
        }
    }

    public function execute(bool $alterBooleanArgs = false)
    {
        if ($alterBooleanArgs) {
            $this->alterBooleanArgs();
        }

        if (!$res = pg_query_params($this->sql, $this->args)) {
            // note pg_last_error seems to often not return anything
            $msg = pg_last_error() . " " . $this->sql . " \nArgs: " . var_export($this->args, true);
            throw new \Exception("Query Execution Failure: $msg");
        }
        return $res;
    }

    /**
     * returns the value of the one column in one record
     * or false if 0 or multiple records result
     */
    public function getOne()
    {
        if ($res = $this->execute()) {
            if (pg_num_rows($res) == 1) {
                // make sure only 1 field in query
                if (pg_num_fields($res) == 1) {
                    return pg_fetch_array($res)[0];
                }
                else {
                    throw new \Exception("Too many result fields");
                }
            }
            else {
                // either 0 or multiple records in result
                // if 0
                if (pg_num_rows($res) == 0) {
                    // no error here. client can error if appropriate
                    return false;
                }
                else {
                    throw new \Exception("Multiple results");
                }
            }
        }
        else {
            // query failed. error triggered already in execute
            return false;
        }
    }

    /**
     * @param string $msg
     * sends pertinent query values to error handler
     */
    public function triggerError($msg = 'Query Failure')
    {
        $errorMsg = "$msg: $this->sql";
        $errorMsg .= "\nArgs: ";
        $errorMsg .= var_export($this->args, true);
        trigger_error($errorMsg);
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
}
