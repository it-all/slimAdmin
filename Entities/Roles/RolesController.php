<?php
declare(strict_types=1);

namespace Entities\Roles;

use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\SlimAdmin;
use Exceptions;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableInsertFormValidator;
use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableUpdateFormValidator;
use Entities\Roles\View\RolesUpdateView;
use Entities\Roles\View\RolesInsertView;
use Entities\Roles\View\RolesListView;
use Infrastructure\BaseEntity\BaseMVC\Controller\ListViewAdminController;

//  extends DatabaseTableController
class RolesController extends ListViewAdminController
{
    public function __construct(Container $container)
    {
        $this->tableMapper = RolesTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ROLES;
        parent::__construct($container);
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $listView = new RolesListView($this->container);
        $this->setIndexFilter($request, $this->getListViewColumns(), $listView);
        return $listView->indexView($response);
    }

    protected function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->tableMapper->getColumns() as $column) {
            $listViewColumns[$column->getName()] = $column->getName();
        }

        return $listViewColumns;
    }

    /** note authorization check handled by middleware */
    public function routePostInsert(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->tableMapper->getBooleanColumnNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->tableMapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $this->requestInput;
            return (new RolesInsertView($this->container))->insertView($request, $response, $args);
        }

        /** if primary key is set the new id is returned by mapper insert method */
        $insertResult = $this->tableMapper->insert($this->requestInput);

        $this->enterEventAndNotice('insert', $insertResult);
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'index')))
            ->withStatus(302);
    }

    /** the table must have a primary key column defined */
    /** note authorization check handled by middleware */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        $primaryKeyValue = $args[ROUTEARG_PRIMARY_KEY];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->tableMapper->getBooleanColumnNames());

        $redirectRoute = SlimAdmin::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (null === $record = $this->tableMapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->tableMapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->tableMapper->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            SlimAdmin::addAdminNotice("No changes made", 'failure');
            return (new RolesUpdateView($this->container))->updateView($request, $response, $args);
            // return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->tableMapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        $this->tableMapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue);

        $this->enterEventAndNotice('update', $primaryKeyValue);
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor($redirectRoute))
            ->withStatus(302);
    }
    
    // override to check exceptions
    public function routeGetDelete(Request $request, Response $response, $args)
    {
        $primaryKey = $args[ROUTEARG_PRIMARY_KEY];
        $tableName = $this->tableMapper->getFormalTableName();
        $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName();

        try {
            $this->tableMapper->deleteByPrimaryKey($primaryKey);
            $this->events->insertInfo(EVENT_ROLE_DELETE, [$primaryKeyColumnName => $primaryKey]);
            SlimAdmin::addAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\UnallowedActionException $e) {
            $this->events->insertWarning(EVENT_UNALLOWED_ACTION, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            $this->events->insertError(EVENT_QUERY_FAIL, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice('Delete Failed', 'failure');
        }
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'index')))
            ->withStatus(302);
    }

    /** called by insert and update */
    private function enterEventAndNotice(string $action, $primaryKeyValue = null) 
    {
        if ($action != 'insert' && $action != 'update') {
            throw new \InvalidArgumentException("Action must be either insert or update");
        }

        $actionPastTense = ($action == 'insert') ? 'inserted' : 'updated';

        $tableNameSingular = $this->tableMapper->getTableNameSingular();
        $noteStart = "$actionPastTense $tableNameSingular";

        /** use event constant if defined, squelch warning */
        $eventTitle = @constant("EVENT_".strtoupper($tableNameSingular)."_".strtoupper($action)) ?? $noteStart;
        $adminNotification = $noteStart;
        $eventPayload = [];

        if (null !== $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $primaryKeyValue"; // if primary key is set the new id is returned by mapper insert method
            $eventPayload = [$primaryKeyColumnName => $primaryKeyValue];
        }
        
        $eventPayload = array_merge($eventPayload, $this->requestInput);

        $this->events->insertInfo($eventTitle, $eventPayload);
        SlimAdmin::addAdminNotice($adminNotification);
    }

}
