<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\App;
use SlimPostgres\DatabaseTableController;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, PermissionsMapper::getInstance(), new PermissionsView($container), ROUTEPREFIX_PERMISSIONS);
    }

    /** override to call objects view */
    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), $this->view);
        return $this->view->indexViewObjects($response);
    }
}
