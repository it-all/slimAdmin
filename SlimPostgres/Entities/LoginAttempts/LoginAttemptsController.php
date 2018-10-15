<?php
declare(strict_types=1);

namespace SlimPostgres\Entities\LoginAttempts;

use SlimPostgres\BaseMVC\Controller\DatabaseTableController;
use Slim\Container;

class LoginAttemptsController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, LoginAttemptsMapper::getInstance(), new LoginAttemptsView($container), 'logins');
    }
}
