<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\ObjectsListViews;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\DatabaseTableView;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class RolesView extends DatabaseTableView implements ObjectsListViews
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesMapper::getInstance(), ROUTEPREFIX_ROLES, true, 'admin/lists/objectsList.php');
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndex($request, Response $response, $args)
    {
        return $this->indexViewObjects($response);
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexViewObjects($response, true);
    }

    /** get role objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $roles = $this->mapper->getObjects($this->getFilterColumnsInfo());
        } catch (\Exception $e) {
            $roles = [];
            // warning is inserted when query fails
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Query Failure', App::STATUS_ADMIN_NOTICE_FAILURE];
        }
        
        return $this->indexView($response, $roles);
    }
}
