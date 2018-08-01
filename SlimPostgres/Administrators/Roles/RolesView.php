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
    public function indexView(Response $response, bool $resetFilter = false, ?string $filterFieldValue = null)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = $this->getFilterColumnsInfo();

        /** save error in var prior to unsetting */
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);
        FormHelper::unsetSessionFormErrors();
        
        $roles = $this->mapper->getObjects($filterColumnsInfo);

        return $this->view->render(
            $response,
            'admin/lists/objectsList.php',
            [
                'title' => $this->mapper->getTableName(),
                'insertLinkInfo' => $this->insertLinkInfo,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $this->getFilterFieldValue(),
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $filterColumnsInfo != null,
                'resetFilterRoute' => $this->filterResetRoute,
                'updateColumn' => $this->updateColumn,
                'updatesPermitted' => $this->updatesPermitted,
                'updateRoute' => $this->updateRoute,
                'deletesPermitted' => $this->deletesPermitted,
                'deleteRoute' => $this->deleteRoute,
                'displayItems' => $roles,
                'columnCount' => count($roles[0]->getListViewFields()),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }
}
