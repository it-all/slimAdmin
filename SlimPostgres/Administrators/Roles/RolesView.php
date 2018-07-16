<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\DatabaseTableListView;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Response;

class RolesView extends DatabaseTableListView
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesMapper::getInstance(), ROUTEPREFIX_ROLES);
    }

    // override in order to not show delete link for roles in use
    public function indexView(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][parent::SESSION_FILTER_COLUMNS_KEY])) ? $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][parent::SESSION_FILTER_COLUMNS_KEY] : null;

        if ($results = pg_fetch_all($this->mapper->select("*", $filterColumnsInfo))) {
            $numResults = count($results);
        } else {
            $results = [];
            $numResults = 0;
        }

        $allowDeleteRoles = [];
        foreach ($results as $row) {
            if (!$this->mapper->hasAdministrator((int) $row['id'])) {
                $allowDeleteRoles[] = $row['id'];
            }
        }

        $filterFieldValue = $this->getFilterFieldValue();
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);

        // make sure all session input necessary to send to template is produced above
        FormHelper::unsetFormSessionVars();

        return $this->view->render(
            $response,
            'admin/lists/rolesList.php',
            [
                'title' => $this->mapper->getTableName(),
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
                'allowDeleteRoles' => $allowDeleteRoles,
                'results' => $results,
                'numResults' => $numResults,
                'numColumns' => $this->mapper->getCountSelectColumns(),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }
}
