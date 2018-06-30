<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Logins;

use SlimPostgres\DatabaseTableController;
use Slim\Container;

class LoginsController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, new LoginsMapper(), new LoginsView($container), 'logins');
    }
}
