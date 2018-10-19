<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\View;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\View\AdminNavigation;
use Slim\Container;

class AdminView extends BaseView
{
    protected $navigationItems;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        if ($this->authentication->isAuthenticated()) {
            $this->navigationItems = (new AdminNavigation($container))->getNavForAdministrator();
        }
    }
}
