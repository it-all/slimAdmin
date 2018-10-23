<?php
declare(strict_types=1);

namespace Entities\LoginAttempts;

use Infrastructure\BaseMVC\Controller\DatabaseTableController;
use Slim\Container;

class LoginAttemptsController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, LoginAttemptsTableMapper::getInstance(), new LoginAttemptsView($container), 'logins');
    }
}
