<?php
declare(strict_types=1);

namespace Entities\Administrators\Model;

use Infrastructure\Database\Postgres;
use Infrastructure\Database\DataMappers\TableMapper;

// fake Singleton with public constructor
final class AdministratorsTableMapper extends TableMapper
{
    const TABLE_NAME = 'administrators';
    const UPDATE_FIELDS = ['name', 'username', 'password', 'active'];

    const ORDER_BY_COLUMN_NAME = 'name';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new AdministratorsTableMapper();
        }
        return $instance;
    }

    public function __construct()
    {
        parent::__construct(self::TABLE_NAME, '*', self::ORDER_BY_COLUMN_NAME);
    }

    // returns hashed password for insert/update 
    public function getHashedPassword(string $password): string 
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function callInsert(string $name, string $username, string $passwordClear, bool $active): int
    {
        $columnValues = [
            'name' => $name,
            'username' => $username,
            'password_hash' => $this->getHashedPassword($passwordClear),
            'active' => Postgres::convertBoolToPostgresBool($active),
        ];

        return (int) parent::insert($columnValues);
    }

    /** deletes the administrators record */
    public function delete(int $administratorId): ?string
    {
        return parent::deleteByPrimaryKey($administratorId, 'username');
    }
    
    public function getChangedFields(array $changedFields): array 
    {
        $changedAdministratorFields = [];

        foreach ($changedFields as $fieldName => $fieldInfo) {
            if (!in_array($fieldName, self::UPDATE_FIELDS)) {
                throw new \InvalidArgumentException("Invalid field $fieldName in changedFields");
            }
            switch($fieldName) {
                case 'password':
                    $changedAdministratorFields['password_hash'] = $this->getHashedPassword($changedFields['password']);
                break;
                case 'active':
                    $changedAdministratorFields['active'] = Postgres::convertBoolToPostgresBool($changedFields['active']);

                break;
                default:
                    $changedAdministratorFields[$fieldName] = $changedFields[$fieldName];
            }
        }

        return $changedAdministratorFields;
    }
}
