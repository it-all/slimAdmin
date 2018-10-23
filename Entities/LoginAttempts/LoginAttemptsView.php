<?php
declare(strict_types=1);

namespace Entities\LoginAttempts;

use Infrastructure\BaseMVC\View\AdminListView;
use Slim\Container;

class LoginAttemptsView extends AdminListView
{
    public function __construct(Container $container)
    {
        parent::__construct($container, 'logins', ROUTE_LOGIN_ATTEMPTS, LoginAttemptsTableMapper::getInstance(), ROUTE_LOGIN_ATTEMPTS_RESET);
    }
}
