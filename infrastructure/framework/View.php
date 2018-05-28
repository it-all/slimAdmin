<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Framework;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/** The base view class */
class View
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
