<?php
declare(strict_types=1);

namespace SlimPostgres\Database\SingleTable;

use SlimPostgres\App;
use SlimPostgres\BaseController;
use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class SingleTableController extends BaseController
{
    protected $model;
    protected $view;
    protected $routePrefix;

    public function __construct(Container $container, $model, $view, $routePrefix)
    {
        $this->model = $model;
        $this->view = $view;
        $this->routePrefix = $routePrefix;
        parent::__construct($container);
    }

    public function getModel(): SingleTableModel
    {
        return $this->model;
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->model->getListViewColumns(), App::getRouteName(true, $this->routePrefix, 'index'), $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->checkFunctionality(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request);
        $this->addBooleanFieldsToInput();

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], FormHelper::getDatabaseTableValidationFields($this->model));

        $this->validator->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->model));

        if (count($this->model->getUniqueColumns()) > 0) {
            $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'Already exists.');

            foreach ($this->model->getUniqueColumns() as $databaseColumnModel) {
                $this->validator->rule('unique', $databaseColumnModel->getName(), $databaseColumnModel, $this->validator);
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

    private function addBooleanFieldsToInput()
    {
        foreach ($this->model->getColumns() as $databaseColumnModel) {
            if ($databaseColumnModel->isBoolean() && !isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$databaseColumnModel->getName()])) {
                $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$databaseColumnModel->getName()] = 'f';
            }
        }
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->checkFunctionality(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request);
        $this->addBooleanFieldsToInput();

        $redirectRoute = App::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key in the model
        if (!$record = $this->model->selectForPrimaryKey($args['primaryKey'])) {
            return SingleTableHelper::updateRecordNotFound($this->container, $response, $args['primaryKey'], $this->model, $this->routePrefix);
        }

        // if no changes made, redirect
        // debatable whether this should be part of validation and stay on page with error
        $changedColumnsValues = $this->getModel()->getChangedColumnsValues($_SESSION[App::SESSION_KEY_REQUEST_INPUT], $record);
        if (count($changedColumnsValues) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made (Record ".$args['primaryKey'].")", 'adminNoticeFailure'];
            FormHelper::unsetFormSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }

        $this->validator = $this->validator->withData($_SESSION[App::SESSION_KEY_REQUEST_INPUT], FormHelper::getDatabaseTableValidationFields($this->model));

        $this->validator->mapFieldsRules(FormHelper::getDatabaseTableValidation($this->model));

        if (count($this->model->getUniqueColumns()) > 0) {
            $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
                if (!$params[1]->errors($field)) {
                    return !$params[0]->recordExistsForValue($value);
                }
                return true; // skip validation if there is already an error for the field
            }, 'Already exists.');

            foreach ($this->model->getUniqueColumns() as $databaseColumnModel) {
                // only set rule for changed columns
                if ($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$databaseColumnModel->getName()] != $record[$databaseColumnModel->getName()]) {
                    $this->validator->rule('unique', $databaseColumnModel->getName(), $databaseColumnModel, $this->validator);
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
        if (!$this->authorization->checkFunctionality(App::getRouteName(true, $this->routePrefix, 'delete'))) {
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
            $res = $this->model->insertRecord($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);
            $returned = pg_fetch_all($res);
            $primaryKeyColumnName = $this->model->getPrimaryKeyColumnName();
            $insertedRecordId = $returned[0][$primaryKeyColumnName];
            $tableName = $this->model->getTableName();

            $this->systemEvents->insertInfo("Inserted $tableName", (int) $this->authentication->getUserId(), "$primaryKeyColumnName:$insertedRecordId");

            if ($sendEmail) {
                $settings = $this->container->get('settings');
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    "Inserted into $tableName.\n See event log for details.",
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
        // attempt to update the model
        try {
            if (count($changedColumnValues) > 0) {
                $updateColumnValues = $changedColumnValues;
                $sendChangedColumnsOnly = false;
            } else {
                $updateColumnValues = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
                $sendChangedColumnsOnly = true;
            }

            $this->model->updateRecordByPrimaryKey($updateColumnValues, $args['primaryKey'], $sendChangedColumnsOnly, $record);

            $primaryKeyColumnName = $this->model->getPrimaryKeyColumnName();
            $updatedRecordId = $args['primaryKey'];
            $tableName = $this->model->getTableName();

            $this->systemEvents->insertInfo("Updated $tableName", (int) $this->authentication->getUserId(), "$primaryKeyColumnName:$updatedRecordId");

            if ($sendEmail) {
                $settings = $this->container->get('settings');
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    "Updated $tableName.\n See event log for details.",
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
        $primaryKeyColumnName = $this->model->getPrimaryKeyColumnName();
        $tableName = $this->model->getTableName();
        $eventNote = "$primaryKeyColumnName:$primaryKey";

        try {
            if (!$res = $this->model->deleteByPrimaryKey($primaryKey, $returnColumn)) {
                $this->systemEvents->insertWarning('Primary key not found for delete', (int) $this->authentication->getUserId(), $eventNote);
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

        $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getUserId(), $eventNote);

        if ($sendEmail) {
            $settings = $this->container->get('settings');
            $this->mailer->send(
                $_SERVER['SERVER_NAME'] . " Event",
                "Deleted record from $tableName.\nSee event log for details.",
                [$settings['emails']['programmer']]
            );
        }

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
        return true;
    }
}
