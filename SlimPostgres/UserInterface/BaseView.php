<?php
declare(strict_types=1);

namespace SlimPostgres\UserInterface;

use Slim\Container;

/** The base view class */
class BaseView
{
    protected $container; // dependency injection container

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __get($name)
    {
        return $this->container->{$name};
    }
}
