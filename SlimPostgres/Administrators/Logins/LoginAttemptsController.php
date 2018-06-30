<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Logins;

use SlimPostgres\DatabaseTableController;
use Slim\Container;

class LoginAttemptsController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, LoginAttemptsMapper::getInstance(), new LoginAttemptsView($container), 'logins');
    }
}
