<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\Exceptions\QueryFailureException;
use SlimPostgres\ObjectsListViews;
use SlimPostgres\InsertUpdateViews;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\DatabaseTableView;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class RolesView extends DatabaseTableView implements ObjectsListViews, InsertUpdateViews
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
        } catch (QueryFailureException $e) {
            $roles = [];
            // warning is inserted when query fails
            App::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $roles);
    }
}
