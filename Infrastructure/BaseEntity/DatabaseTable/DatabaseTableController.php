<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable;

use Error;
use Exception;
use Infrastructure\SlimAdmin;
use Exceptions;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\BaseMVC\Controller\AdminController;
use Infrastructure\BaseEntity\BaseMVC\Controller\ListViewAdminController;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableInsertFormValidator;
use Infrastructure\BaseEntity\DatabaseTable\Model\DatabaseTableUpdateFormValidator;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableListView;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableInsertView;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableUpdateView;

class DatabaseTableController extends ListViewAdminController
{
    use ResponseUtilities;

    private $mapper;

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($tableName)) {
            throw new \Exception("Database table does not exist: ".$tableName);
        }

        $this->mapper = new TableMapper($tableName);
        $listView = new DatabaseTableListView($this->container, $tableName);
        $this->setIndexFilter($request, $this->getListViewColumns(), $listView);
        return $listView->indexView($response);
    }

    public function getMapper(): TableMapper
    {
        return $this->mapper;
    }

    protected function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->mapper->getColumns() as $column) {
            $listViewColumns[$column->getName()] = $column->getName();
        }

        return $listViewColumns;
    }

    /** note authorization check handled by middleware */
    public function routePostInsert(Request $request, Response $response, $args)
    {
        $tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($tableName)) {
            throw new \Exception("Database table does not exist: ".$tableName);
        }

        $this->mapper = new TableMapper($tableName);
        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper));
        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->mapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $this->requestInput;
            return (new DatabaseTableInsertView($this->container, $tableName))->insertView($request, $response, $args);
        }

        /** if primary key is set the new id is returned by mapper insert method */
        $insertResult = $this->mapper->insert($this->requestInput);

        $this->enterEventAndNotice('insert', $insertResult);
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES, [ROUTEARG_DATABASE_TABLE_NAME => $tableName]))
            ->withStatus(302);
    }

    /** called by insert and update */
    private function enterEventAndNotice(string $action, $primaryKeyValue = null) 
    {
        if (!in_array($action, ['insert', 'update', 'delete'])) {
            throw new \InvalidArgumentException("Action must be either insert, update, or delete");
        }

        $actionPastTense = ($action == 'insert') ? 'inserted to' : $action . 'd';
        $noteStart = "$actionPastTense " . $this->mapper->getTableName();

        /** use event constant if defined */
        try {
            $eventTitle = constant("EVENT_".strtoupper($this->mapper->getTableNameSingular())."_".strtoupper($action));
        } catch (Error $e) {
            $eventTitle = $noteStart;
        }
        $adminNotification = $noteStart;
        $eventPayload = [];

        if (null !== $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $primaryKeyValue"; // if primary key is set the new id is returned by mapper insert method
            $eventPayload = [$primaryKeyColumnName => $primaryKeyValue];
        }
        
        if (is_array($this->requestInput)) {
            $eventPayload = array_merge($eventPayload, $this->requestInput);
        }

        $this->events->insertInfo($eventTitle, $eventPayload);
        SlimAdmin::addAdminNotice($adminNotification);
    }

    /** the table must have a primary key column defined */
    /** note authorization check handled by middleware */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        $tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($tableName)) {
            throw new \Exception("Database table does not exist: ".$tableName);
        }

        $this->mapper = new TableMapper($tableName);

        $primaryKeyValue = $args[ROUTEARG_PRIMARY_KEY];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper));

        // make sure there is a record for the primary key
        if (null === $record = $this->mapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->mapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            SlimAdmin::addAdminNotice("No changes made", 'failure');
            return (new DatabaseTableUpdateView($this->container, $tableName, $primaryKeyValue))->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->mapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $this->requestInput;
            return (new DatabaseTableUpdateView($this->container, $tableName, $primaryKeyValue))->updateView($request, $response, $args);
        }

        $this->mapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue, true, null, true);

        $this->enterEventAndNotice('update', $primaryKeyValue);
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES, [ROUTEARG_DATABASE_TABLE_NAME => $tableName]))
            ->withStatus(302);
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        $tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($tableName)) {
            throw new \Exception("Database table does not exist: ".$tableName);
        }

        $this->mapper = new TableMapper($tableName);

        $primaryKey = $args[ROUTEARG_PRIMARY_KEY];
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();

        try {
            $this->mapper->deleteByPrimaryKey($primaryKey);
            $this->enterEventAndNotice('delete', $primaryKey);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, ['table' => $tableName, $primaryKeyColumnName => $primaryKey]);
            SlimAdmin::addAdminNotice("$tableName $primaryKey Not Found", 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            $this->events->insertError(EVENT_QUERY_FAIL, ['action' => 'delete', 'table' => $tableName, $primaryKeyColumnName => $primaryKey, 'msg' => $e->getMessage()]);
            SlimAdmin::addAdminNotice('Deletion Query Failure', 'failure');
        }
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES, [ROUTEARG_DATABASE_TABLE_NAME => $tableName]))
            ->withStatus(302);
    }
}
