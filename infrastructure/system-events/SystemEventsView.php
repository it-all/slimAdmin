<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\System_Events;

use It_All\Slim_Postgres\Infrastructure\ListView;
use Slim\Container;

class SystemEventsView extends ListView
{
    public function __construct(Container $container)
    {
        // model already in container as a service
        parent::__construct($container, 'systemEvents', ROUTE_SYSTEM_EVENTS, $container->systemEvents, ROUTE_SYSTEM_EVENTS_RESET);
    }
}
