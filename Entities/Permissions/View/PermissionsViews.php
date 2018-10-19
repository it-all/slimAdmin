<?php
declare(strict_types=1);

namespace Entities\Permissions\View;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\View\ObjectsListViews;
use Infrastructure\BaseMVC\View\AdminListView;
use Infrastructure\BaseMVC\View\InsertUpdateViews;
use Infrastructure\BaseMVC\View\ResponseUtilities;
use Entities\Permissions\Model\PermissionsMapper;
use Entities\Permissions\View\Forms\PermissionInsertForm;
use Entities\Permissions\View\Forms\PermissionUpdateForm;
use Exceptions\QueryFailureException;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsViews extends AdminListView implements ObjectsListViews, InsertUpdateViews
{
    use ResponseUtilities;

    const FILTER_FIELDS_PREFIX = 'permissions';

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_PERMISSIONS;

        parent::__construct($container, self::FILTER_FIELDS_PREFIX, ROUTE_ADMINISTRATORS_PERMISSIONS, PermissionsMapper::getInstance(), ROUTE_ADMINISTRATORS_PERMISSIONS_RESET, 'admin/lists/objectsList.php');

        $this->setInsert();
        $this->setUpdate();
        $this->setDelete();
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return PERMISSIONS_INSERT_RESOURCE;
                break;
            case 'update':
                return PERMISSIONS_UPDATE_RESOURCE;
                break;
            case 'delete':
                return PERMISSIONS_DELETE_RESOURCE;
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

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formAction = $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'insert', 'post'));
        $fieldValues = ($request->isPost() && isset($args[SlimPostgres::USER_INPUT_KEY])) ? $args[SlimPostgres::USER_INPUT_KEY] : [];

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->mapper->getFormalTableName(false),
                'form' => (new PermissionInsertForm($formAction, $this->container, $fieldValues))->getForm(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        // make sure there is a permission for the primary key
        if (null === $permission = $this->mapper->getObjectById((int) $args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->mapper->getPrimaryTableMapper(), 'update');
        }

        $formAction = $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $args['primaryKey']]);

        /** if input set we are redisplaying after invalid form submission */
        if ($request->isPut() && isset($args[SlimPostgres::USER_INPUT_KEY])) {
            $updateForm = new PermissionUpdateForm($formAction, $this->container, $args[SlimPostgres::USER_INPUT_KEY]);
        } else {
            $updateForm = new PermissionUpdateForm($formAction, $this->container);
            $updateForm->setFieldValuesToPermission($permission);
        }

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Update ' . $this->mapper->getPrimaryTableMapper()->getFormalTableName(false),
                'form' => $updateForm->getForm(),
                // 'form' => $this->getForm($request, 'update', (int) $args['primaryKey'], $administrator),
                'primaryKey' => $args['primaryKey'],
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true
            ]
        );
    }

    /** get permissions objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $permissions = $this->mapper->getObjects($this->getFilterColumnsInfo());
        } catch (QueryFailureException $e) {
            $permissions = [];
            // warning system event is inserted when query fails
            SlimPostgres::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $permissions);
    }
}
