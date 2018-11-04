<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\BaseEntity\BaseMVC\Controller\AdminController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class EventsController extends AdminController
{
    private $view;
    private $mapper;

    public function __construct(Container $container)
    {
        $this->view = new EventsView($container);
        parent::__construct($container);
        $this->mapper = \Entities\Events\EventsEntityMapper::getInstance();
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->mapper::SELECT_COLUMNS, $this->view);
        return $this->view->indexView($response);
    }
}
