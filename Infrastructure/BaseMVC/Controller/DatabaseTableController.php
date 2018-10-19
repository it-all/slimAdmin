<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\Controller;

use Infrastructure\SlimPostgres;
use Exceptions;
use Infrastructure\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseMVC\Controller\BaseController;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseMVC\View\Forms\DatabaseTableForm;
use Infrastructure\Validation\DatabaseTableInsertFormValidator;
use Infrastructure\Validation\DatabaseTableUpdateFormValidator;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class DatabaseTableController extends BaseController
{
    use ResponseUtilities;

    protected $mapper;
    protected $view;
    protected $routePrefix;

    public function __construct(Container $container, $mapper, $view, $routePrefix)
    {
        $this->mapper = $mapper;
        $this->view = $view;
        $this->routePrefix = $routePrefix;
        parent::__construct($container);
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

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->mapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $insertResult = $this->mapper->insert($this->requestInput);
        } catch (\Exception $e) {
            throw new \Exception("Insert failure. ".$e->getMessage());
        }

        $noteStart = "Inserted " . $this->mapper->getFormalTableName(false);
        $adminNotification = $noteStart;
        $eventNote = "";

        if (null !== $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $insertResult"; // if primary key is set the new id is returned by mapper insert method
            $eventNote = "$primaryKeyColumnName: $insertResult";
        }
        
        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);
        SlimPostgres::setAdminNotice($adminNotification);

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }

    public function getBooleanFieldNames(): array
    {
        $booleanFieldNames = [];
        foreach ($this->mapper->getColumns() as $column) {
            if ($column->isBoolean()) {
                $booleanFieldNames[] = $column->getName();
            }
        }
        return $booleanFieldNames;
    }

    /** the table must have a primary key column defined */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_UPDATE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKeyValue = $args['primaryKey'];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $redirectRoute = SlimPostgres::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (!$record = $this->mapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->mapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            SlimPostgres::setAdminNotice("No changes made", 'failure');
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->mapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        try {
            $this->mapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue);
        } catch (\Exception $e) {
            throw new \Exception("Update failure. ".$e->getMessage());
        }

        $noteStart = "Updated " . $this->mapper->getFormalTableName(false);
        $adminNotification = "$noteStart $primaryKeyValue";
        $eventNote = $this->mapper->getPrimaryKeyColumnName() . ": " . $primaryKeyValue;

        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);
        SlimPostgres::setAdminNotice($adminNotification);

        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_DELETE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->mapper->getFormalTableName(false);
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();

        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey);
            $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning('Delete Attempt on Non-existing Record', (int) $this->authentication->getAdministratorId(), "Table: $tableName|$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("$tableName $primaryKey Not Found", 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            SlimPostgres::setAdminNotice('Deletion Query Failure', 'failure');
        }

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }

    private function getChangedFieldsString(array $changedFields, array $record): string 
    {
        $changedString = "";
        foreach ($changedFields as $fieldName => $newValue) {
            $changedString .= " $fieldName: ".$record[$fieldName]." => $newValue, ";
        }

        return substr($changedString, 0, strlen($changedString)-2);
    }
}
