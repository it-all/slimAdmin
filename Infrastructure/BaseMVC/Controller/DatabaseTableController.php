<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\Controller;

use Infrastructure\SlimPostgres;
use Exceptions;
use Infrastructure\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseMVC\Controller\AdminController;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseMVC\View\Forms\DatabaseTableForm;
use Infrastructure\Validation\DatabaseTableInsertFormValidator;
use Infrastructure\Validation\DatabaseTableUpdateFormValidator;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class DatabaseTableController extends AdminController
{
    use ResponseUtilities;

    protected $tableMapper;
    protected $view;
    protected $routePrefix;

    public function __construct(Container $container, TableMapper $tableMapper, $view, $routePrefix)
    {
        $this->tableMapper = $tableMapper;
        $this->view = $view;
        $this->routePrefix = $routePrefix;
        parent::__construct($container);
    }

    public function getMapper(): TableMapper
    {
        return $this->tableMapper;
    }

    protected function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->tableMapper->getColumns() as $column) {
            $listViewColumns[$column->getName()] = $column->getName();
        }

        return $listViewColumns;
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), $this->view);
        return $this->view->indexView($response);
    }

    public function routePostInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_INSERT_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        /** note that boolean columns that don't exist in request input are added as false */
        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->tableMapper->getBooleanColumnNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->tableMapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->insertView($request, $response, $args);
        }

        /** if primary key is set the new id is returned by mapper insert method */
        $insertResult = $this->tableMapper->insert($this->requestInput);

        $this->enterEventAndNotice('insert', $insertResult);

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }

    /** called by insert and update */
    private function enterEventAndNotice(string $action, $primaryKeyValue = null) 
    {
        if ($action != 'insert' && $action != 'update') {
            throw new \InvalidArgumentException("Action must be either insert or update");
        }

        $actionPastTense = ($action == 'insert') ? 'inserted' : 'updated';

        $tableNameSingular = $this->tableMapper->getFormalTableName(false);
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
        SlimPostgres::setAdminNotice($adminNotification);
    }

    /** the table must have a primary key column defined */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_UPDATE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKeyValue = $args['primaryKey'];

        /** note that boolean columns that don't exist in request input are added as false */
        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->tableMapper->getBooleanColumnNames());

        $redirectRoute = SlimPostgres::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (null === $record = $this->tableMapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->tableMapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            SlimPostgres::setAdminNotice("No changes made", 'failure');
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->tableMapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        $this->tableMapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue);

        $this->enterEventAndNotice('update', $primaryKeyValue);

        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_DELETE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->tableMapper->getFormalTableName(false);
        $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName();

        try {
            $this->tableMapper->deleteByPrimaryKey($primaryKey);

            /** use constant if defined, squelch warning */
            $eventTitle = @constant("EVENT_".strtoupper($tableName)."_DELETE") ?? "Deleted $tableName";

            $this->events->insertInfo($eventTitle, [$primaryKeyColumnName => $primaryKey]);
            SlimPostgres::setAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, ['table' => $tableName, $primaryKeyColumnName => $primaryKey]);
            SlimPostgres::setAdminNotice("$tableName $primaryKey Not Found", 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            SlimPostgres::setAdminNotice('Deletion Query Failure', 'failure');
        }

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }
}
