<?php
declare(strict_types=1);

namespace Entities\Permissions\Model;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\Database\Postgres;

// Singleton
final class PermissionsTableMapper extends TableMapper
{
    const TABLE_NAME = 'permissions';
    const UPDATE_FIELDS = ['title', 'description', 'active'];

    const ORDER_BY_COLUMN_NAME = 'title';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new PermissionsTableMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        parent::__construct(self::TABLE_NAME, '*', self::ORDER_BY_COLUMN_NAME);
    }

    public function callInsert(string $title, ?string $description = null, bool $active = true): int
    {
        $columnValues = [
            'title' => $title,
            'description' => $description,
            'active' => Postgres::convertBoolToPostgresBool($active),
        ];

        return (int) parent::insert($columnValues);
    }

    public function isUpdatable(): bool
    {
        return true;
    }

    public function isDeletable(): bool 
    {
        return true;
    }
    
    public function getChangedFields(array $changedFields): array 
    {
        $changedPermissionFields = [];

        foreach ($changedFields as $fieldName => $fieldInfo) {
            if (!in_array($fieldName, self::UPDATE_FIELDS)) {
                throw new \InvalidArgumentException("Invalid field $fieldName in changedFields");
            }
            switch($fieldName) {
                case 'active':
                    $changedPermissionFields['active'] = Postgres::convertBoolToPostgresBool($changedFields['active']);

                break;
                default:
                    $changedPermissionFields[$fieldName] = $changedFields[$fieldName];
            }
        }
        return $changedPermissionFields;
    }
    
    /** deletes the permissions record */
    public function delete(int $permissionId): ?string
    {
        return parent::deleteByPrimaryKey($administratorId, 'title');
    }
}
