<?php
declare(strict_types=1);

namespace SlimPostgres\SystemEvents;

use SlimPostgres\Controller;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class SystemEventsController extends Controller
{
    private $view;
    private $model;

    public function __construct(Container $container)
    {
        $this->view = new SystemEventsView($container);
        parent::__construct($container);
        $this->model = $this->systemEvents; // already in container as a service
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->model::SELECT_COLUMNS, ROUTE_SystemEvents, $this->view);
    }
}
