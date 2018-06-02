<?php
declare(strict_types=1);

namespace Domain\Administrators;

use SlimPostgres\App;
use SlimPostgres\Controller;
use SlimPostgres\Database\SingleTable\SingleTableController;
use SlimPostgres\Database\SingleTable\SingleTableHelper;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsController extends Controller
{
    private $administratorsModel;
    private $view;
    private $routePrefix;
    private $administratorsSingleTableController;

    public function __construct(Container $container)
    {
        $this->administratorsModel = new AdministratorsModel();
        $this->view = new AdministratorsView($container);
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        $this->administratorsSingleTableController = new SingleTableController($container, $this->administratorsModel->getPrimaryTableModel(), $this->view, $this->routePrefix);
        parent::__construct($container);
    }

    private function setValidation(array $record = null)
    {
        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
        $this->validator = $this->validator->withData($input);

        // bool - either inserting or !inserting (editing)
        $inserting = $record == null;

        // define unique column rule to be used in certain situations below
        $this->validator::addRule('unique', function($field, $value, array $params = [], array $fields = []) {
            if (!$params[1]->errors($field)) {
                return !$params[0]->recordExistsForValue($value);
            }
            return true; // skip validation if there is already an error for the field
        }, 'Already exists.');

        // name field is required (insert and edit)
        $this->validator->rule('required', 'name');
        $this->validator->rule('regex', 'name', '%^[a-zA-Z\s]+$%')->message('must be letters and spaces only');
        $this->validator->rule('required', ['username', 'role_id']);
        $this->validator->rule('lengthMin', 'username', 4);
        if ($inserting || strlen($input['password']) > 0) {
            $this->validator->rule('required', ['password', 'password_confirm']);
            // https://stackoverflow.com/questions/8141125/regex-for-password-php
//            $this->validator->rule('regex', 'password', '%^\S*(?=\S{4,})\S*$%')->message('Must be at least 12 characters long');
            $this->validator->rule('lengthMin', 'username', 4);
            $this->validator->rule('equals', 'password', 'password_confirm')->message('must be the same as Confirm Password');
        }

        // unique column rule for username if it has changed
        if ($inserting || $record['username'] != $input['username']) {
            $this->validator->rule('unique', 'username', $this->administratorsModel->getPrimaryTableModel()->getColumnByName('username'), $this->validator);
        }
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->administratorsModel::SELECT_COLUMNS, ROUTE_ADMINISTRATORS, $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->checkFunctionality(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request);
        // no boolean fields to add

        $this->setValidation();

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
            return $this->view->getInsert($request, $response, $args);
        }

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];
        if (!$res = $this->administratorsModel->insert($input['name'], $input['username'], $input['password'], (int) $input['role_id'])) {
            throw new \Exception("Insert Failure");
        }

        $returned = pg_fetch_all($res);
        $insertedRecordId = $returned[0]['id'];

        $this->systemEvents->insertInfo("Inserted admin", (int) $this->authentication->getUserId(), "id:$insertedRecordId");

        FormHelper::unsetSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted record $insertedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->checkFunctionality(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        $this->setRequestInput($request);
        // no boolean fields

        $redirectRoute = App::getRouteName(true, $this->routePrefix,'index');

        // make sure there is a record for the primary key in the model
        if (!$record = $this->administratorsModel->getPrimaryTableModel()->selectForPrimaryKey($primaryKey)) {
            return SingleTableHelper::updateRecordNotFound($this->container, $response, $primaryKey, $this->administratorsModel->getPrimaryTableModel(), $this->routePrefix);
        }

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        // if no changes made, redirect
        // note, if pw and pwconf fields are blank, do not include them in changed fields check
        // debatable whether this should be part of validation and stay on page with error
        $checkChangedFields = [
            'username' => $input['username'],
            'role_id' => $input['role_id'],
            'name' => $input['name']
        ];
        if (strlen($input['password']) > 0 || strlen($input['password_confirm']) > 0) {
            // password_hash to match db column name
            $checkChangedFields['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        if (!$this->administratorsSingleTableController->haveAnyFieldsChanged($checkChangedFields, $record)) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made (Record $primaryKey)", App::STATUS_ADMIN_NOTICE_FAILURE];
            FormHelper::unsetSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }

        $this->setValidation($record);

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
            return $this->view->updateView($request, $response, $args);
        }

        if (!$this->administratorsModel->updateByPrimaryKey((int) $primaryKey, $input['name'], $input['username'], (int) $input['role_id'], $input['password'], $record)) {
            throw new \Exception("Update Failure");
        }

        $this->systemEvents->insertInfo("Updated ".$this->administratorsModel::TABLE_NAME, (int) $this->authentication->getUserId(), "id:$primaryKey");

        FormHelper::unsetSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated record $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    // override for custom validation and return column
    public function getDelete(Request $request, Response $response, $args)
    {
        // make sure the current admin is not deleting themself
        if ((int) ($args['primaryKey']) == $this->container->authentication->getUserId()) {
            throw new \Exception('You cannot delete yourself from administrators');
        }

        // make sure there are no system events for admin being deleted
        if ($this->container->systemEvents->hasForAdmin((int) $args['primaryKey'])) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["System Events exist for admin id ".$args['primaryKey'], App::STATUS_ADMIN_NOTICE_FAILURE];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        return $this->administratorsSingleTableController->getDeleteHelper($response, $args['primaryKey'],'username', true);
    }
}
