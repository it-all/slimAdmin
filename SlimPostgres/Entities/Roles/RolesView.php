<?php
declare(strict_types=1);

namespace SlimPostgres\Entities\Roles;

use SlimPostgres\Entities\Roles\Model\RolesMapper;
use SlimPostgres\App;
use SlimPostgres\Exceptions\QueryFailureException;
use SlimPostgres\BaseMVC\View\ObjectsListViews;
use SlimPostgres\BaseMVC\View\InsertUpdateViews;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\BaseMVC\View\DatabaseTableView;
use SlimPostgres\BaseMVC\View\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class RolesView extends DatabaseTableView implements ObjectsListViews, InsertUpdateViews
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesMapper::getInstance(), ROUTEPREFIX_ROLES, true, 'admin/lists/objectsList.php');
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return ROLES_INSERT_RESOURCE;
                break;
            case 'update':
                return ROLES_UPDATE_RESOURCE;
                break;
            case 'delete':
                return ROLES_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
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
