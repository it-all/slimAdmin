<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\Controller;

use Psr\Container\ContainerInterface as Container;

abstract class AdminController extends BaseController
{
    public function __construct(Container $container)
    {
        $container->get('events')->setAdministratorId($container->get('authentication')->getAdministratorId());
        parent::__construct($container);
    }
}
