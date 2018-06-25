<?php
declare(strict_types=1);

namespace SlimPostgres\SystemEvents;

use SlimPostgres\Controllers\BaseController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class SystemEventsController extends BaseController
{
    private $view;
    private $mapper;

    public function __construct(Container $container)
    {
        $this->view = new SystemEventsView($container);
        parent::__construct($container);
        $this->mapper = $this->systemEvents; // already in container as a service
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->mapper::SELECT_COLUMNS, ROUTE_SYSTEM_EVENTS, $this->view);
    }
}
