<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\BaseMVC\View\AdminListView;
use Slim\Container;

class EventsView extends AdminListView
{
    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_EVENTS;
        // events mapper already in container as a service
        parent::__construct($container, 'events', ROUTE_EVENTS, $container->events, ROUTE_EVENTS_RESET);
    }
}
