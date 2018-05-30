<?php
declare(strict_types=1);

namespace SlimPostgres;

use Domain\NavAdmin;
use Slim\Container;

class AdminView extends View
{
    protected $navigationItems;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        // Instantiate navigation navbar contents
        $navAdmin = new NavAdmin($container);
        $this->navigationItems = $navAdmin->getNavForUser();
    }

    protected function getPermissions(string $type = 'index')
    {
        if (!isset($this->routePrefix)) {
            throw new \Exception("The routePrefix property must be set.");
        }

        if ($type != 'index' && $type != 'insert' && $type != 'update' && $type != 'delete') {
            throw new \Exception("Invalid type $type");
        }

        return $this->container->authorization->getPermissions(App::getRouteName(true, $this->routePrefix, $type));
    }
}
