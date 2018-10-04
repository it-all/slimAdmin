<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Exceptions\QueryFailureException;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\DataMappers\TableMappers;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class AdminListView extends AdminView
{
    /** user entered value of the filter field. subkey of request input */
    protected $sessionFilterFieldKey;

    protected $filterKey;

    protected $indexRoute;
    protected $mapper;

    /** defaults to resultsList but objectsList can be passed in */
    protected $template;
    protected $filterResetRoute;

    /** false or ['text' => {link text}, 'route' => {route}] */
    protected $insertLinkInfo; 

    protected $updatesPermitted;
    protected $updateColumn;
    protected $updateRoute;
    protected $deletesPermitted;
    protected $deleteRoute;
    
    const SESSION_FILTER_COLUMNS_KEY = 'columns';
    const SESSION_FILTER_VALUE_KEY = 'value';

    public function __construct(Container $container, string $filterFieldsPrefix, string $indexRoute, TableMappers $mapper, string $filterResetRoute, string $template = 'admin/lists/resultsList.php')
    {
        parent::__construct($container);
        $this->sessionFilterFieldKey = $filterFieldsPrefix . 'Filter';
        $this->filterKey = $filterFieldsPrefix;

        $this->indexRoute = $indexRoute;
        $this->mapper = $mapper;
        $this->template = $template;
        $this->filterResetRoute = $filterResetRoute;

        /** initialize insert, update, delete properties to disallow. children can override by calling setInsert, setUpdate, setDelete methods */
        $this->insertLinkInfo = null; 
        $this->updatesPermitted = false; 
        $this->updateColumn = null; 
        $this->updateRoute = null; 
        $this->deletesPermitted = false; 
        $this->deleteRoute = null; 
        
    }

    public function getFilterKey(): string 
    {
        return $this->filterKey;
    }

    protected function setInsert()
    {
        $this->insertLinkInfo = ($this->authorization->isAuthorized($this->getPermissions('insert'))) ? [
            'text' => 'Insert '.$this->mapper->getFormalTableName(false), 
            'route' => App::getRouteName(true, $this->routePrefix, 'insert')
        ] : null;
    }

    protected function setUpdate()
    {
        $this->updateColumn = $this->mapper->getUpdateColumnName();

        $this->updatesPermitted = $this->authorization->isAuthorized($this->getPermissions('update')) && $this->updateColumn !== null;

        $this->updateRoute = App::getRouteName(true, $this->routePrefix, 'update');
    }

    protected function setDelete()
    {
        $this->deletesPermitted = $this->container->authorization->isAuthorized($this->getPermissions('delete'));

        $this->deleteRoute = App::getRouteName(true, $this->routePrefix, 'delete');
    }

    public function routeIndex(Request $request, Response $response, $args)
    {
        return $this->indexView($response);
    }

    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        return $this->resetFilter($response, $this->indexRoute);
    }

    /** get display items (array of recordset) from the mapper. special handling when filtering */
    private function getDisplayItems(): array 
    {
        /** squelch the sql warning in case of ill-formed filter field and catch the exception instead in order to alert the administrator of mistake. note, ideally any value that causes a query failure will be invalidated in the controller, but this is an extra measure of avoiding an unhandled exception while still logging/displaying the alert */
        if (null !== $filterColumnsInfo = $this->getFilterColumnsInfo()) {
            try {
                $pgResults = @$this->mapper->select($this->mapper->getSelectColumnsString(), $filterColumnsInfo);
            } catch (QueryFailureException $e) {
                $this->systemEvents->insertAlert("List View Filter Query Failure", (int) $this->authentication->getAdministratorId(), $e->getMessage());
                App::setAdminNotice('Query Failed', 'failure');
                $displayItems = [];
            }
        } else {
            $pgResults = $this->mapper->select($this->mapper->getSelectColumnsString());
        }

        if (isset($pgResults)) {
            if (!$displayItems = pg_fetch_all($pgResults)) {
                /** no results for query */
                $displayItems = [];
            }
            pg_free_result($pgResults);
        }

        return $displayItems;
    }

    /** display items can be passed in as an array of records or objects, if objects, the appropriate template should be passed to this constructor. */
    public function indexView(Response $response, ?array $displayItems = null)
    {
        /** if display items have not been passed in, get them from the mapper */
        if ($displayItems === null) {
            $displayItems = $this->getDisplayItems();
        }

        /** save error in var prior to unsetting */
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);
        FormHelper::unsetSessionFormErrors();

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
                'isFiltered' => $this->getFilterFieldValue() != '',
                'resetFilterRoute' => $this->filterResetRoute,
                'updatesPermitted' => $this->updatesPermitted,
                'updateColumn' => $this->updateColumn,
                'updateRoute' => $this->updateRoute,
                'deletesPermitted' => $this->deletesPermitted,
                'deleteRoute' => $this->deleteRoute,
                'displayItems' => $displayItems,
                'columnCount' => $this->mapper->getCountSelectColumns(),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    protected function getFilterColumnsInfo(): ?array 
    {
        return (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY])) ? $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY] : null;
    }

    /** either session value or empty string */
    protected function getFilterFieldValue(): string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY])) {
            return $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY];
        } else {
            return '';
        }
    }
        
    protected function resetFilter(Response $response, string $redirectRoute)
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY])) {
            unset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY]);
        }

        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY])) {
            unset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY]);
        }

        // redirect to the clean url
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function getSessionFilterFieldKey(): string
    {
        return $this->sessionFilterFieldKey;
    }
}
