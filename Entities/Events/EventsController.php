<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\BaseEntity\BaseMVC\Controller\ListViewAdminController;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class EventsController extends ListViewAdminController
{
    private $mapper;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->mapper = \Entities\Events\EventsEntityMapper::getInstance();
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $listView = new EventsListView($this->container);
        $this->setIndexFilter($request, $this->mapper::SELECT_COLUMNS, $listView);
        return $listView->indexView($response);
    }
}
