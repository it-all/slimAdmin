<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Logins;

use Slim\Container;

class LoginsView extends \SlimPostgres\ListView
{
    public function __construct(Container $container)
    {
        parent::__construct($container, 'logins', ROUTE_LOGIN_ATTEMPTS, new LoginsModel(), ROUTE_LOGIN_ATTEMPTS_RESET);
    }
}
