<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\BaseMVC\Controller\BaseController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class EventsController extends BaseController
{
    private $view;
    private $mapper;

    public function __construct(Container $container)
    {
        $this->view = new EventsView($container);
        parent::__construct($container);
        $this->mapper = $this->events; // already in container as a service
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->mapper::SELECT_COLUMNS, $this->view);
        return $this->view->indexView($response);
    }
}
