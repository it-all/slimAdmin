<?php
declare(strict_types=1);

namespace SlimPostgres\Controllers;

use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Controllers\BaseController;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\UserInterface\Forms\FormHelper;
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

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], FormHelper::getDatabaseTableValidationFields($this->mapper));

        $this->validator->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->mapper));

        if (count($this->mapper->getUniqueColumns()) > 0) {
            $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'Already exists.');

            foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
                $this->validator->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this->validator);
            }
        }

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
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
            // return SingleTableHelper::updateRecordNotFound($this->container, $response, $args['primaryKey'], $this->mapper, $this->routePrefix);
        }

        // if no changes made, redirect
        // debatable whether this should be part of validation and stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $record);
        if (count($changedColumnsValues) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made (Record ".$args['primaryKey'].")", 'adminNoticeFailure'];
            FormHelper::unsetFormSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], FormHelper::getDatabaseTableValidationFields($this->mapper));

        $this->validator->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->mapper));

        if (count($this->mapper->getUniqueColumns()) > 0) {
            $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'Already exists.');

            foreach ($this->mapper->getUniqueColumns() as $databaseColumnMapper) {
                // only set rule for changed columns
                if ($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$databaseColumnMapper->getName()] != $record[$databaseColumnMapper->getName()]) {
                    $this->validator->rule('unique', $databaseColumnMapper->getName(), $databaseColumnMapper, $this->validator);
                }
            }
        }

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
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
        return $this->getDeleteHelper($response, $args['primaryKey']);
    }

    public function getDeleteHelper(Response $response, $primaryKey, string $returnColumn = null, bool $sendEmail = false, $routeType = 'index')
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        try {
            $this->delete($primaryKey, $returnColumn, $sendEmail);
        } catch (\Exception $e) {
            // no need to do anything, just redirect with error message already set
        }

        $redirectRoute = App::getRouteName(true, $this->routePrefix, $routeType);
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    protected function insert(bool $sendEmail = false)
    {
        // attempt insert
        try {
            $res = $this->mapper->insert($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);
            $returned = pg_fetch_all($res);
            $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();
            $insertedRecordId = $returned[0][$primaryKeyColumnName];
            $tableName = $this->mapper->getTableName();

            $this->systemEvents->insertInfo("Inserted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName:$insertedRecordId");

            if ($sendEmail) {
                $settings = $this->container->get('settings');
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    "Inserted into $tableName." . PHP_EOL . " See event log for details.",
                    [$settings['emails']['programmer']]
                );
            }

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted record $insertedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];

            return true;

        } catch(\Exception $exception) {
            throw $exception;
        }
    }

    protected function update(Response $response, $args, array $changedColumnValues = [], array $record = [], bool $sendEmail = false)
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
            $tableName = $this->mapper->getTableName();

            $this->systemEvents->insertInfo("Updated $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName:$updatedRecordId");

            if ($sendEmail) {
                $settings = $this->container->get('settings');
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    "Updated $tableName." . PHP_EOL . " See event log for details.",
                    [$settings['emails']['programmer']]
                );
            }

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated record $updatedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];

            return true;

        } catch(\Exception $exception) {
            throw $exception;
        }
    }

    protected function delete($primaryKey, string $returnColumn = null, bool $sendEmail = false)
    {
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();
        $tableName = $this->mapper->getTableName();
        $eventNote = "$primaryKeyColumnName:$primaryKey";

        try {
            if (!$res = $this->mapper->deleteByPrimaryKey($primaryKey, $returnColumn)) {
                $this->systemEvents->insertWarning('Primary key not found for delete', (int) $this->authentication->getAdministratorId(), $eventNote);
                $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$primaryKey.' not found', 'adminNoticeFailure'];
                return false;
            }
        } catch (\Exception $e) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Query Failure', 'adminNoticeFailure'];
            return false;
        }

        $adminMessage = 'Deleted record '.$primaryKey;
        if ($returnColumn != null) {
            $returned = pg_fetch_all($res);
            $eventNote .= "|$returnColumn:".$returned[0][$returnColumn];
            $adminMessage .= " ($returnColumn ".$returned[0][$returnColumn].")";
        }

        $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), $eventNote);

        if ($sendEmail) {
            $settings = $this->container->get('settings');
            $this->mailer->send(
                $_SERVER['SERVER_NAME'] . " Event",
                "Deleted record from $tableName." . PHP_EOL . "See event log for details.",
                [$settings['emails']['programmer']]
            );
        }

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
        return true;
    }
}
