<?php
declare(strict_types=1);

namespace SlimPostgres\SystemEvents;

use SlimPostgres\ListView;
use Slim\Container;

class SystemEventsView extends ListView
{
    public function __construct(Container $container)
    {
        // model already in container as a service
        parent::__construct($container, 'systemEvents', ROUTE_SystemEvents, $container->systemEvents, ROUTE_SystemEvents_RESET);
    }
}
