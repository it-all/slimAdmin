<?php
declare(strict_types=1);

namespace Infrastructure\Database;

use Infrastructure\Database\Queries\QueryBuilder;

/**
 * Class Postgres
 * A class for connecting to a postgresql database and a few useful meta-query methods
 */
Class Postgres
{
    /** @var array http://www.postgresql.org/docs/9.4/static/datatype-numeric.html */
    const NUMERIC_TYPES = array('smallint', 'integer', 'bigint', 'decimal', 'numeric', 'real', 'double precision', 'smallserial', 'serial', 'bigserial');

    const INTEGER_TYPES = array('smallint', 'integer', 'bigint', 'smallserial', 'serial', 'bigserial');

    const SMALLINT_MIN = -32768;
    const SMALLINT_MAX = 32767;

    const INTEGER_MIN = -2147483648;
    const INTEGER_MAX = 2147483647;

    const BIGINT_MIN = -9223372036854775808;
    const BIGINT_MAX = 9223372036854775807;

    const SMALLSERIAL_MIN = 1;
    const SMALLSERIAL_MAX = self::SMALLINT_MAX;

    const SERIAL_MIN = self::SMALLSERIAL_MIN;
    const SERIAL_MAX = self::INTEGER_MAX;

    const BIGSERIAL_MIN = self::SMALLSERIAL_MIN;
    const BIGSERIAL_MAX = self::BIGINT_MAX;

    const BOOLEAN_FALSE = 'f';
    const BOOLEAN_TRUE = 't';

    private static $connection;

    private function __construct(string $connectionString = '')
    {
        if (!self::$connection = pg_connect($connectionString)) {
            throw new \Exception('Postgres connection failure');
        }
        pg_set_error_verbosity(self::$connection, PGSQL_ERRORS_VERBOSE);
    }

    public static function getInstance(string $connectionString = '')
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Postgres($connectionString);
        }
        return $instance;
    }

    public function getConnection(string $connectionString = '') {
        return self::$connection;
    }

    /**
     * select all tables in a schema
     * @param string $schema
     * @return array of table names
     */
    public static function getSchemaTables(string $schema = 'public', array $skipTables = []): array
    {
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = $1";
        foreach ($skipTables as $skip) {
            $query .= " AND table_name";
            $query .= (substr($skip, mb_strlen($skip) - 1) === '%') ? " NOT LIKE '$skip'" : " != '$skip'";
        }
        $query .= " ORDER BY table_name";
        $q = new QueryBuilder($query, $schema);

        $pgResult = $q->execute();
        $records = pg_fetch_all($pgResult);
        pg_free_result($pgResult);
        $tables = [];
        foreach ($records as $index => $record) {
            $tables[] = $record['table_name'];
        }
        return array_values($tables);
    }

    /**
     * determines if db table exists
     * @param optional string $tableName
     * @param string $schema
     * @return bool
     */
    public function doesTableExist(string $tableName, string $schema = 'public'): bool
    {
        $q = new QueryBuilder("SELECT table_name FROM information_schema.tables WHERE table_name = $1 AND table_type = 'BASE TABLE' AND table_schema = $2", $tableName, $schema);

        if (pg_num_rows($q->execute()) == 0) {
            return false;
        }
        return true;
    }

    /** note: NOT enough info given by pg_meta_data($tableName); */
    public static function getTableMetaData(string $tableName)
    {
        $q = new QueryBuilder("SELECT column_name, data_type, column_default, is_nullable, character_maximum_length, numeric_precision, udt_name FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = $1", $tableName);

        $rs = $q->execute();
        if (pg_num_rows($rs) == 0) {
            throw new \InvalidArgumentException();
        }

        return $rs;
    }

    public static function convertPostgresBoolToBool(string $pgBool): bool 
    {
        if ($pgBool !== self::BOOLEAN_TRUE && $pgBool !== self::BOOLEAN_FALSE) {
            throw new \InvalidArgumentException("pgBool must be valid postgres boolean");
        }

        return $pgBool === self::BOOLEAN_TRUE;
    }

    public static function convertBoolToPostgresBool(bool $bool): string 
    {
        return ($bool) ? self::BOOLEAN_TRUE : self::BOOLEAN_FALSE;
    }
}
