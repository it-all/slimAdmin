<?php
declare(strict_types=1);

namespace SlimPostgres\UserInterface\Views;

use SlimPostgres\App;
use SlimPostgres\UserInterface\Views\Navigation\AdminNavigation;
use Slim\Container;

class AdminView extends BaseView
{
    protected $navigationItems;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->navigationItems = (new AdminNavigation($container))->getNavForUser();
    }

    protected function getPermissions(string $routeType = 'index')
    {
        if (!isset($this->routePrefix)) {
            throw new \Exception("The routePrefix property must be set.");
        }

        if (!in_array($routeType, App::VALID_ROUTE_TYPES)) {
            throw new \Exception("Invalid route type $routeType");
        }

        return $this->container->authorization->getPermissions(App::getRouteName(true, $this->routePrefix, $routeType));
    }
}
