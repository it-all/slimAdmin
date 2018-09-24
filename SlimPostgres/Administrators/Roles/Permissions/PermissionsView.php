<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\App;
use SlimPostgres\ObjectsListViews;
use SlimPostgres\DatabaseTableView;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsView extends DatabaseTableView implements ObjectsListViews
{
    public function __construct(Container $container)
    {
        parent::__construct($container, PermissionsMapper::getInstance(), ROUTEPREFIX_PERMISSIONS, true, 'admin/lists/objectsList.php');
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

    /** get permissions objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $permissions = $this->mapper->getObjects($this->getFilterColumnsInfo());
        } catch (\Exception $e) {
            $permissions = [];
            // warning system event is inserted when query fails
            App::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $permissions);
    }
}
