<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Framework;

/** The base middleware class that can be extended by any registered middleware which needs access to the slim container */
class Middleware
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }
}
