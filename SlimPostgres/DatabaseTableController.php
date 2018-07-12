<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Exceptions;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Forms\FormHelper;
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

    private function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->mapper->getColumns() as $column) {
            $listViewColumns[$column->getName()] = $column->getName();
        }

        return $listViewColumns;
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), App::getRouteName(true, $this->routePrefix, 'index'), $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, $this->getBooleanFieldNames());

        $validator = new DatabaseTableInsertFormValidator($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $this->mapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $this->insert();
        } catch (\Exception $e) {
            throw new \Exception("Insert failure. ".$e->getMessage());
        }

        FormHelper::unsetFormSessionVars();
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

    public function putUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, $this->getBooleanFieldNames());

        $redirectRoute = App::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (!$record = $this->mapper->selectForPrimaryKey($args['primaryKey'])) {
            return $this->databaseRecordNotFound($response, $args['primaryKey'], $this->mapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $record);
        if (count($changedColumnsValues) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made", 'adminNoticeFailure'];
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $this->mapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->updateView($request, $response, $args);
        }

        try {
            $this->update($response, $args, $changedColumnsValues, $record);
        } catch (\Exception $e) {
            throw new \Exception("Update failure. ".$e->getMessage());
        }

        FormHelper::unsetFormSessionVars();
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function getDelete(Request $request, Response $response, $args)
    {
        return $this->deleteHelper($response, $args['primaryKey']);
    }

    /**
     * this can be called by child classes
     * $emailTo is an email title from $settings['emails']
     */
    public function deleteHelper(Response $response, $primaryKey, ?string $returnColumn = null, ?string $emailTo = null, $routeType = 'index')
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $this->delete($primaryKey, $returnColumn, $emailTo); // sets success or failure notices

        $redirectRoute = App::getRouteName(true, $this->routePrefix, $routeType);
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    /**
     * $emailTo is an email title from $settings['emails']
     */
    protected function insert(?string $emailTo = null)
    {
        // attempt insert
        try {
            $res = $this->mapper->insert($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);
            $returned = pg_fetch_all($res);
            $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();
            $insertedRecordId = $returned[0][$primaryKeyColumnName];
            $tableName = $this->mapper->getTableName(false);

            $this->systemEvents->insertInfo("Inserted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName:$insertedRecordId");

            if ($emailTo !== null) {
                $settings = $this->container->get('settings');
                if (isset($settings['emails'][$emailTo])) {
                    $this->mailer->send(
                        $_SERVER['SERVER_NAME'] . " Event",
                        "Inserted $tableName" . PHP_EOL . "See event log for details.",
                        [$settings['emails'][$emailTo]]
                    );
                } else {
                    $this->systemEvents->insertInfo("Invalid email", (int) $this->authentication->getAdministratorId(), $emailTo);
                }
            }

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted $tableName $insertedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];

            return true;

        } catch(\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * $emailTo is an email title from $settings['emails']
     */
    protected function update(Response $response, $args, array $changedColumnValues = [], array $record = [], ?string $emailTo = null)
    {
        // attempt to update
        try {
            if (count($changedColumnValues) > 0) {
                $updateColumnValues = $changedColumnValues;
                $sendChangedColumnsOnly = false;
            } else {
                $updateColumnValues = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
                $sendChangedColumnsOnly = true;
            }

            $this->mapper->updateByPrimaryKey($updateColumnValues, $args['primaryKey'], $sendChangedColumnsOnly, $record);

            $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();
            $updatedRecordId = $args['primaryKey'];
            $tableName = $this->mapper->getTableName(false);

            $this->systemEvents->insertInfo("Updated $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName:$updatedRecordId");

            if ($emailTo !== null) {
                $settings = $this->container->get('settings');
                if (isset($settings['emails'][$emailTo])) {
                    $this->mailer->send(
                        $_SERVER['SERVER_NAME'] . " Event",
                        "Updated $tableName" . PHP_EOL . "See event log for details.",
                        [$settings['emails'][$emailTo]]
                    );
                } else {
                    $this->systemEvents->insertInfo("Invalid email", (int) $this->authentication->getAdministratorId(), $emailTo);
                }
            }

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated $tableName $updatedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];

            return true;

        } catch(\Exception $exception) {
            throw $exception;
        }
    }

    /**
     * $emailTo is an email title from $settings['emails']
     */
    protected function delete($primaryKey, ?string $returnColumn = null, ?string $emailTo = null)
    {
        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey, $returnColumn);
        } catch (Exceptions\QueryResultsNotFoundException $e) {

            // enter system event
            $this->systemEvents->insertWarning('Query Results Not Found', (int) $this->authentication->getAdministratorId(), $this->mapper->getPrimaryKeyColumnName().":$primaryKey|Table:".$this->mapper->getTableName());

            // set admin notice
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$primaryKey.' not found', 'adminNoticeFailure'];
            throw $e;

        } catch (\Exception $e) {

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Deletion Failure', 'adminNoticeFailure'];
            throw $e;
            
        }

        $this->deleted($dbResult, $primaryKey, $returnColumn, $emailTo);
    }

    /**
     * call after a record has been successfully deleted
     * $emailTo is an email title from $settings['emails']
     */
    protected function deleted($dbResult, $primaryKey, ?string $returnColumn = null, ?string $emailTo = null)
    {
        $tableName = $this->mapper->getTableName(false);
        $eventNote = $this->mapper->getPrimaryKeyColumnName().":$primaryKey";

        $adminMessage = "Deleted $tableName $primaryKey";
        if ($returnColumn != null) {
            $returned = pg_fetch_all($dbResult);
            $eventNote .= "|$returnColumn:".$returned[0][$returnColumn];
            $adminMessage .= " ($returnColumn ".$returned[0][$returnColumn].")";
        }

        $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), $eventNote);

        if ($emailTo !== null) {
            $settings = $this->container->get('settings');
            if (isset($settings['emails'][$emailTo])) {
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    "Deleted $tableName" . PHP_EOL . "See event log for details.",
                    [$settings['emails'][$emailTo]]
                );
            } else {
                $this->systemEvents->insertInfo("Invalid email", (int) $this->authentication->getAdministratorId(), $emailTo);
            }
        }

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
    }
}
