<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Logins;

use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Queries\QueryBuilder;

class LoginsModel extends SingleTableModel
{
    public function __construct()
    {
        parent::__construct('login_attempts', '*', 'created', false);
    }

    public function insertSuccessfulLogin(string $username, int $adminId)
    {
        $this->insert(true,$username, $adminId);
    }

    public function insertFailedLogin(string $username, int $adminId = null)
    {
        $this->insert(false, $username, $adminId);
    }

    private function insert(bool $success, string $username, int $adminId = null)
    {
        // bool must be converted to pg bool format
        $successPg = ($success) ? 't' : 'f';

        $q = new QueryBuilder("INSERT INTO login_attempts (admin_id, username, ip, success, created) VALUES($1, $2, $3, $4, NOW())", $adminId, $username, $_SERVER['REMOTE_ADDR'], $successPg);
        return $q->execute();
    }

    public function getView()
    {
        $q = new QueryBuilder("SELECT id, admin_id, username, ip as ip_address, created as time_stamp, success FROM login_attempts ORDER BY created DESC");
        return $q->execute();
    }
}
