<?php
declare(strict_types=1);

namespace SlimPostgres\SystemEvents;

use SlimPostgres\UserInterface\AdminListView;
use Slim\Container;

class SystemEventsView extends AdminListView
{
    public function __construct(Container $container)
    {
        // systemEvents mapper already in container as a service
        parent::__construct($container, 'systemEvents', ROUTE_SYSTEM_EVENTS, $container->systemEvents, ROUTE_SYSTEM_EVENTS_RESET);
    }
}
