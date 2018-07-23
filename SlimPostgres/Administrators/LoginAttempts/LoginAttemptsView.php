<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\LoginAttempts;

use SlimPostgres\AdminListView;
use Slim\Container;

class LoginAttemptsView extends AdminListView
{
    public function __construct(Container $container)
    {
        parent::__construct($container, 'logins', ROUTE_LOGIN_ATTEMPTS, LoginAttemptsMapper::getInstance(), ROUTE_LOGIN_ATTEMPTS_RESET);
    }
}
