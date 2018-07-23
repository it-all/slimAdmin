<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\LoginAttempts;

use SlimPostgres\ListViewMappers;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Database\Postgres;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;

// Singleton
final class LoginAttemptsMapper extends TableMapper
{
    const TABLE_NAME = 'login_attempts';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new LoginAttemptsMapper();
        }
        return $instance;
    }

    private function __construct()
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
        // bool must be converted to pg bool format
        $successPg = ($success) ? Postgres::BOOLEAN_TRUE : Postgres::BOOLEAN_TRUE;

        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (administrator_id, username, ip, success, created) VALUES($1, $2, $3, $4, NOW())", $administratorId, $username, $_SERVER['REMOTE_ADDR'], $successPg);
        return $q->execute();
    }

    public function getView()
    {
        $q = new QueryBuilder("SELECT id, administrator_id, username, ip as ip_address, created as time_stamp, success FROM ".self::TABLE_NAME." ORDER BY created DESC");
        return $q->execute();
    }

    public function hasAdministrator(int $administratorId): bool 
    {
        $q = new QueryBuilder("SELECT COUNT(id) FROM ".self::TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        return (bool) $q->getOne();
    }
}
