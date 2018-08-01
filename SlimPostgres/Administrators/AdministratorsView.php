<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use It_All\FormFormer\Fieldset;
use SlimPostgres\Administrators\Administrator;
use SlimPostgres\Administrators\Forms\AdministratorInsertForm;
use SlimPostgres\Administrators\Forms\AdministratorUpdateForm;
use SlimPostgres\Administrators\Roles\Roles;
use SlimPostgres\Administrators\Roles\RolesMapper;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Form;
use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\AdminListView;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsView extends AdminListView
{
    use ResponseUtilities;

    protected $routePrefix;

    public function __construct(Container $container)
    {
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;

        parent::__construct($container, 'administrators', ROUTE_ADMINISTRATORS, AdministratorsMapper::getInstance(), ROUTE_ADMINISTRATORS_RESET, 'admin/lists/objectsList.php');

        $insertLinkInfo = ($this->authorization->isAuthorized($this->getPermissions('insert'))) ? ['text' => 'Insert '.$this->mapper->getPrimaryTableName(false), 'route' => App::getRouteName(true, $this->routePrefix, 'insert')] : false;
        $this->setInsert($insertLinkInfo);

        $this->setUpdate($this->authorization->isAuthorized($this->getPermissions('update')), $this->mapper->getUpdateColumnName(), App::getRouteName(true, $this->routePrefix, 'update'));

        $this->setDelete($this->container->authorization->isAuthorized($this->getPermissions('delete')), App::getRouteName(true, $this->routePrefix, 'delete'));
    }

    /**
     * override in order to populate roles with multiple if necessary
     * @param Response $response
     * @param bool $resetFilter
     * @return AdministratorsView|AdminListView
     */
    public function indexView(Response $response, bool $resetFilter = false, ?string $filterFieldValue = null)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = $this->getFilterColumnsInfo();

        /** save error in var prior to unsetting */
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);
        FormHelper::unsetSessionFormErrors();

        // make sure all session input necessary to send to template is produced above
        FormHelper::unsetSessionFormErrors();

        $administrators = $this->mapper->getObjects($filterColumnsInfo, null, $this->authentication, $this->authorization);

        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->mapper->getFormalTableName(),
                'insertLinkInfo' => $this->insertLinkInfo,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $this->getFilterFieldValue(),
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $filterColumnsInfo != null,
                'resetFilterRoute' => $this->filterResetRoute,
                'updatesPermitted' => $this->updatesPermitted,
                'updateColumn' => $this->updateColumn,
                'updateRoute' => $this->updateRoute,
                'deletesPermitted' => $this->deletesPermitted,
                'deleteRoute' => $this->deleteRoute,
                'displayItems' => $administrators,
                'columnCount' => count($administrators[0]->getListViewFields()),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
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
                'title' => 'Insert '. $this->mapper->getPrimaryTableName(false),
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
