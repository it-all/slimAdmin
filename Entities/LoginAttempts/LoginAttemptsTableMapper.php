<?php
declare(strict_types=1);

namespace Entities\LoginAttempts;

use Entities\Administrators\Model\Administrator;
use Infrastructure\Database\Postgres;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\Database\Queries\QueryBuilder;

// Singleton
final class LoginAttemptsTableMapper extends TableMapper
{
    const TABLE_NAME = 'login_attempts';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new LoginAttemptsTableMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        parent::__construct(self::TABLE_NAME, '*', 'created', false);
    }

    public function insertSuccessfulLogin(Administrator $administrator)
    {
        $this->insertLoginAttempt(true, $administrator->getUsername(), $administrator->getId());
    }

    /**
     * This handles both a know administrator (valid username but incorrect password) and an unknown administrator (username not found)
     * @param string $username
     * @param null|Administrator $administrator
     */
    public function insertFailedLogin(string $username, ?Administrator $administrator)
    {
        $id = ($administrator !== null) ? $administrator->getId() : null;
        $this->insertLoginAttempt(false, $username, $id);
    }

    private function insertLoginAttempt(bool $success, string $username, ?int $administratorId)
    {
        $columnValues = [
            'administrator_id' => $administratorId,
            'username' => $username,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'success' => Postgres::convertBoolToPostgresBool($success),
            'created' => 'NOW()',
        ];
        return parent::insert($columnValues);
    }

    public function hasAdministrator(int $administratorId): bool 
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM ".self::TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        return (bool) $q->getOne();
    }
}
