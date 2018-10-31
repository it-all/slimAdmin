<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\Controller;

use Slim\Container;

abstract class AdminController extends BaseController
{
    public function __construct(Container $container)
    {
        $container->events->setAdministratorId($container->authentication->getAdministratorId());
        parent::__construct($container);
    }
}
