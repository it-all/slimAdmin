<?php
declare(strict_types=1);

namespace Entities\Events;

use Entities\Events\EventsEntityMapper;
use Infrastructure\BaseEntity\BaseMVC\View\AdminListView;
use Slim\Container;

class EventsView extends AdminListView
{
    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_EVENTS;
        parent::__construct($container, 'events', ROUTE_EVENTS, EventsEntityMapper::getInstance(), ROUTE_EVENTS_RESET);
    }
}
