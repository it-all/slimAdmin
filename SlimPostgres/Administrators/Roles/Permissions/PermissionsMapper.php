<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Postgres;

// Singleton
final class PermissionsMapper extends TableMapper
{
    /** array id => [permission] */
    private $permissions;

    const TABLE_NAME = 'permissions';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new PermissionsMapper();
        }
        return $instance;
    }

    private function __construct()
    {
        // note that the roles select must be ordered by level (ascending) for getBaseLevel() to work
        parent::__construct(self::TABLE_NAME);
        $this->setPermissions();
    }

    // this is called by constructor and also should be called after a change to roles from single page app to reset them.
    public function setPermissions()
    {
        $this->permissions = [];
        $rs = $this->select();
        while ($row = pg_fetch_array($rs)) {
            $this->permissions[(int) $row['id']] = [
                'permission' => $row['permission'],
            ];
        }
    }

    public function getObject(int $primaryKey): ?Permission 
    {
        if ($record = $this->selectForPrimaryKey($primaryKey)) {
            return $this->buildPermissionFromRecord($record);
        }

        return null;
    }

    private function buildPermissionFromRecord(array $record): Permission 
    {
        foreach ($this->getColumns() as $column) {
            $columnName = $column->getName();
            if (!array_key_exists($columnName, $record)) {
                throw new \InvalidArgumentException("$columnName must exist in record");
            }
        }
        return new Permission((int) $record['id'], $record['permission'], $record['description'], Postgres::convertPostgresBoolToBool($record['active']), new \DateTimeImmutable($record['created']));
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function isUpdatable(): bool
    {
        return true;
    }

    public function isDeletable(int $id): bool 
    {
        return true;
    }

    /** selects and converts recordset to array of objects and return */
    public function getObjects(array $whereColumnsInfo = null): array 
    {
        $permissions = [];

        if ($pgResults = $this->select("*", $whereColumnsInfo)) {
            if (pg_num_rows($pgResults) > 0) {
                while ($record = pg_fetch_assoc($pgResults)) {
                    $permissions[] = $this->buildPermissionFromRecord($record);
                }
            }
        }
        return $permissions;
    }

    /** override to ignore created column */
    public function getColumns(): array
    {
        $columns = [];
        foreach (parent::getColumns() as $column) {
            if ($column->getName() != 'created') {
                $columns[] = $column;
            }
        }
        return $columns;
    }
}
