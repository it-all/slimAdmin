<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
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
    protected $template;
    protected $filterResetRoute;

    /** false or ['text' => {link text}, 'route' => {route}] */
    protected $insertLink; 

    protected $updatePermitted;
    protected $updateColumn;
    protected $updateRoute;
    protected $addDeleteColumn;
    protected $deleteRoute;
    
    const SESSION_FILTER_COLUMNS_KEY = 'columns';
    const SESSION_FILTER_VALUE_KEY = 'value';

    public function __construct(Container $container, string $filterFieldsPrefix, string $indexRoute, TableMappers $mapper, string $filterResetRoute, string $template = 'admin/lists/list.php')
    {
        $this->sessionFilterFieldKey = $filterFieldsPrefix . 'Filter';
        $this->filterKey = $filterFieldsPrefix;

        $this->indexRoute = $indexRoute;
        $this->mapper = $mapper;
        $this->template = $template;
        $this->filterResetRoute = $filterResetRoute;
        $this->updatePermitted = false; // initialize
        $this->updateColumn = null; // initialize
        $this->updateRoute = null; // initialize
        $this->addDeleteColumn = false; // initialize
        $this->deleteRoute = null; // initialize
        $this->insertLink = false; // initialize
        parent::__construct($container);
    }

    public function getFilterKey(): string 
    {
        return $this->filterKey;
    }

    protected function setInsert($insertLink)
    {
        $this->insertLink = $insertLink;
    }

    protected function setUpdate(bool $updatePermitted, ?string $updateColumn, ?string $updateRoute)
    {
        $this->updatePermitted = $updatePermitted; // initialize
        $this->updateColumn = $updateColumn; // initialize
        $this->updateRoute = $updateRoute; // initialize
    }

    protected function setDelete(bool $addDeleteColumn, ?string $deleteRoute)
    {
        if ($addDeleteColumn && $deleteRoute == null) {
            throw new \Exception("delete route must be defined");
        }
        $this->addDeleteColumn = $addDeleteColumn;
        $this->deleteRoute = $deleteRoute;
    }

    public function index(Request $request, Response $response, $args)
    {
        return $this->indexView($response);
    }

    public function indexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexView($response, true);
    }

    public function indexView(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY])) ? $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY] : null;

        if ($results = pg_fetch_all($this->mapper->select($this->mapper->getSelectColumnsString(), $filterColumnsInfo))) {
            $numResults = count($results);
        } else {
            $results = [];
            $numResults = 0;
        }

        $filterFieldValue = $this->getFilterFieldValue();
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);

        // make sure all session input necessary to send to template is produced above
        FormHelper::unsetFormSessionVars();

        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->mapper->getFormalTableName(),
                'insertLink' => $this->insertLink,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $filterFieldValue,
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $filterColumnsInfo,
                'resetFilterRoute' => $this->filterResetRoute,
                'updateColumn' => $this->updateColumn,
                'updatePermitted' => $this->updatePermitted,
                'updateRoute' => $this->updateRoute,
                'addDeleteColumn' => $this->addDeleteColumn,
                'deleteRoute' => $this->deleteRoute,
                'results' => $results,
                'numResults' => $numResults,
                'numColumns' => $this->mapper->getCountSelectColumns(),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
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

    // new input takes precedence over session value
    protected function getFilterFieldValue(): string
    {
        if (isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$this->sessionFilterFieldKey])) {
            return $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$this->sessionFilterFieldKey];
        } elseif (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY])) {
            return $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY];
        } else {
            return '';
        }
    }

    public function getSessionFilterFieldKey(): string
    {
        return $this->sessionFilterFieldKey;
    }
}
