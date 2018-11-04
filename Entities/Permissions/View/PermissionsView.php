<?php
declare(strict_types=1);

namespace Entities\Permissions\View;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseEntity\BaseMVC\View\ObjectsListViews;
use Infrastructure\BaseEntity\BaseMVC\View\AdminListView;
use Infrastructure\BaseEntity\BaseMVC\View\InsertUpdateViews;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Entities\Permissions\Model\PermissionsTableMapper;
use Entities\Permissions\View\Forms\PermissionInsertForm;
use Entities\Permissions\View\Forms\PermissionUpdateForm;
use Exceptions\QueryFailureException;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsView extends AdminListView implements ObjectsListViews, InsertUpdateViews
{
    use ResponseUtilities;

    private $permissionsEntityMapper;
    private $permissionsTableMapper;
    const FILTER_FIELDS_PREFIX = 'permissions';

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_PERMISSIONS;
        $this->permissionsEntityMapper = PermissionsEntityMapper::getInstance();
        $this->permissionsTableMapper = PermissionsTableMapper::getInstance();

        parent::__construct($container, self::FILTER_FIELDS_PREFIX, ROUTE_ADMINISTRATORS_PERMISSIONS, $this->permissionsEntityMapper, ROUTE_ADMINISTRATORS_PERMISSIONS_RESET, 'admin/lists/objectsList.php');

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
    public function routeIndex(Request $request, Response $response, $args)
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
                'title' => $this->permissionsEntityMapper->getInsertTitle(),
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
        if (null === $permission = $this->permissionsEntityMapper->getObjectById((int) $args[ROUTEARG_PRIMARY_KEY])) {
            return $this->databaseRecordNotFound($response, $args[ROUTEARG_PRIMARY_KEY], PermissionsTableMapper::getInstance(), 'update');
        }

        $formAction = $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'update', 'put'), [ROUTEARG_PRIMARY_KEY => $args[ROUTEARG_PRIMARY_KEY]]);

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
                'title' => $this->permissionsEntityMapper->getUpdateTitle(),
                'form' => $updateForm->getForm(),
                'primaryKey' => $args[ROUTEARG_PRIMARY_KEY],
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
            $permissions = $this->permissionsEntityMapper->getObjects($this->getFilterColumnsInfo());
        } catch (QueryFailureException $e) {
            $permissions = [];
            // warning event is inserted when query fails
            SlimPostgres::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $permissions);
    }
}
