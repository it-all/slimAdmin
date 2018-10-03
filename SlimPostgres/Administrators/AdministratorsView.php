<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fieldset;
use SlimPostgres\Exceptions\QueryFailureException;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\Forms\AdministratorInsertForm;
use SlimPostgres\Administrators\Forms\AdministratorUpdateForm;
use SlimPostgres\Administrators\Roles\Roles;
use SlimPostgres\Administrators\Roles\RolesMapper;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Form;
use SlimPostgres\App;
use SlimPostgres\ObjectsListViews;
use SlimPostgres\InsertUpdateViews;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\AdminListView;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsView extends AdminListView implements ObjectsListViews, InsertUpdateViews
{
    use ResponseUtilities;

    protected $routePrefix;

    const FILTER_FIELDS_PREFIX = 'administrators';

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;

        parent::__construct($container, self::FILTER_FIELDS_PREFIX, ROUTE_ADMINISTRATORS, AdministratorsMapper::getInstance(), ROUTE_ADMINISTRATORS_RESET, 'admin/lists/objectsList.php');

        $insertLinkInfo = ($this->authorization->isAuthorized($this->getPermissions('insert'))) ? [
            'text' => 'Insert '.$this->mapper->getFormalTableName(false), 
            'route' => App::getRouteName(true, $this->routePrefix, 'insert')
        ] : null;
        $this->setInsert($insertLinkInfo);

        $this->setUpdate($this->authorization->isAuthorized($this->getPermissions('update')), $this->mapper->getUpdateColumnName(), App::getRouteName(true, $this->routePrefix, 'update'));

        $this->setDelete($this->container->authorization->isAuthorized($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));
    }

    /** overrides in order to get administrator objects and send to indexView */
    public function routeIndex($request, Response $response, $args)
    {
        return $this->indexViewObjects($response);
    }

    /** overrides in order to get administrator objects and send to indexView */
    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexViewObjects($response, true);
    }

    /** get objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $administrators = $this->mapper->getObjects($this->getFilterColumnsInfo(), null, $this->authentication, $this->authorization);
        } catch (QueryFailureException $e) {
            $administrators = [];
            // warning system event is inserted when query fails
            App::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $administrators);
    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formAction = $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'insert', 'post'));
        $fieldValues = ($request->isPost() && isset($args[App::USER_INPUT_KEY])) ? $args[App::USER_INPUT_KEY] : [];

        return $this->view->render(
            $response,
            'admin/form.php',
            [
                'title' => 'Insert '. $this->mapper->getFormalTableName(false),
                'form' => (new AdministratorInsertForm($formAction, $this->container, $fieldValues))->getForm(),
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
        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->mapper->getObjectById((int) $args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->mapper->getPrimaryTableMapper(), 'update');
        }

        $formAction = $this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'update', 'put'), ['primaryKey' => $args['primaryKey']]);

        /** if input set we are redisplaying after invalid form submission */
        if ($request->isPut() && isset($args[App::USER_INPUT_KEY])) {
            $updateForm = new AdministratorUpdateForm($formAction, $this->container, $args[App::USER_INPUT_KEY]);
        } else {
            $updateForm = new AdministratorUpdateForm($formAction, $this->container);
            $updateForm->setFieldValuesToAdministrator($administrator);
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
}
