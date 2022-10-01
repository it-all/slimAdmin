<?php
declare(strict_types=1);

namespace Infrastructure;

use Psr\Container\ContainerInterface;

/** The base middleware class that can be extended by any registered middleware which needs access to application services */
class Middleware
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
