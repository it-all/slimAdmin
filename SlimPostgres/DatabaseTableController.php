<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Exceptions;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Forms\FormHelper;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\DatabaseTableInsertFormValidator;
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
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->mapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $insertResult = $this->mapper->insert($this->requestInput);
        } catch (\Exception $e) {
            throw new \Exception("Insert failure. ".$e->getMessage());
        }

        $noteStart = "Inserted " . $this->mapper->getTableName(false);
        $adminNotification = $noteStart;
        $eventNote = "";

        if (null !== $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $insertResult"; // if primary key is set the new id is returned by mapper insert method
            $eventNote = "$primaryKeyColumnName: $insertedRecordId";
        }
        
        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminNotification, App::STATUS_ADMIN_NOTICE_SUCCESS];

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
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
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKeyValue = $args['primaryKey'];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $redirectRoute = App::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (!$record = $this->mapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->mapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made", App::STATUS_ADMIN_NOTICE_FAILURE];
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->mapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        try {
            $this->mapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue);
        } catch (\Exception $e) {
            throw new \Exception("Update failure. ".$e->getMessage());
        }

        $noteStart = "Updated " . $this->mapper->getTableName(false);
        $adminNotification = "$noteStart $primaryKeyValue";
        $eventNote = $this->mapper->getPrimaryKeyColumnName() . ": " . $primaryKeyValue;

        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminNotification, App::STATUS_ADMIN_NOTICE_SUCCESS];

        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->mapper->getTableName(false);
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();

        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey);
            $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName: $primaryKey");
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Deleted $tableName $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning('Delete Attempt on Non-existing Record', (int) $this->authentication->getAdministratorId(), "Table: $tableName|$primaryKeyColumnName: $primaryKey");
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["$tableName $primaryKey Not Found", App::STATUS_ADMIN_NOTICE_FAILURE];
        } catch (Exceptions\QueryFailureException $e) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Deletion Query Failure', App::STATUS_ADMIN_NOTICE_FAILURE];
        }

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
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
