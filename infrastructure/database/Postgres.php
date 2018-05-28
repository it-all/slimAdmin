<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Database;

use It_All\Slim_Postgres\Infrastructure\Database\Queries\QueryBuilder;

/**
 * Class Postgres
 * @package It_All\Spaghettify\ServicePg
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

    private $pgConn;

    /** host and password may not be necessary depending on hba.conf */
    public function __construct(string $connectionString = '')
    {
        if (!$this->pgConn = pg_connect($connectionString)) {
            throw new \Exception('postgres connection failure');
        }
    }

    public function getPgConn() {
        return $this->pgConn;
    }

    /**
     * select all tables in a schema
     * @param string $schema
     * @return recordset
     */
    public function getSchemaTables(string $schema = 'public', array $skipTables = [])
    {
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = $1";
        foreach ($skipTables as $sk) {
            $query .= " AND table_name";
            $query .= (substr($sk, mb_strlen($sk) - 1) === '%') ? " NOT LIKE '$sk'" : " != '$sk'";
        }
        $query .= " ORDER BY table_name";
        $q = new QueryBuilder($query, $schema);

        return $q->execute();
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
            return false;
        }

        return $rs;
    }
}
