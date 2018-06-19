<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\Roles\RolesModel;
use SlimPostgres\App;
use SlimPostgres\BaseController;
use SlimPostgres\Database\SingleTable\SingleTableController;
use SlimPostgres\Database\SingleTable\SingleTableHelper;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsController extends BaseController
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

    private function setValidation(array $input, array $record = null)
    {
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

        $this->validator->rule('required', ['name', 'username', 'roles']);
        $this->validator->rule('regex', 'name', '%^[a-zA-Z\s]+$%')->message('must be letters and spaces only');
        $this->validator->rule('lengthMin', 'username', 4);
        if ($inserting || mb_strlen($input['password']) > 0) {
            $this->validator->rule('required', ['password', 'password_confirm']);
            // https://stackoverflow.com/questions/8141125/regex-for-password-php
//            $this->validator->rule('regex', 'password', '%^\S*(?=\S{4,})\S*$%')->message('Must be at least 12 characters long');
            $this->validator->rule('lengthMin', 'password', 4);
            $this->validator->rule('equals', 'password', 'password_confirm')->message('must be the same as Confirm Password');
        }

        // unique column rule for username if it has changed
        if ($inserting || $record['username'] != $input['username']) {
            $this->validator->rule('unique', 'username', $this->administratorsModel->getPrimaryTableModel()->getColumnByName('username'), $this->validator);
        }

        // all selected roles must be in roles table
        $this->validator->rule('array', 'roles');
        $rolesModel = new RolesModel();
        $this->validator->rule('in', 'roles.*', array_keys($rolesModel->getRoles())); // role ids
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->administratorsModel::SELECT_COLUMNS, ROUTE_ADMINISTRATORS, $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request); // no boolean fields to add

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        $this->setValidation($input);

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
            return $this->view->getInsert($request, $response, $args);
        }

        if (!$administratorId = $this->administratorsModel->create($input['name'], $input['username'], $input['password'], $input['roles'])) {
            throw new \Exception("Insert Failure");
        }

        $this->systemEvents->insertInfo("Inserted admin", (int) $this->authentication->getAdministratorId(), "id:$administratorId");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted record $administratorId", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        $this->setRequestInput($request);
        // no boolean fields to add

        $redirectRoute = App::getRouteName(true, $this->routePrefix,'index');

        // make sure there is a record for the primary key in the model
        if (!$record = $this->administratorsModel->getPrimaryTableModel()->selectForPrimaryKey($primaryKey)) {
            return SingleTableHelper::updateRecordNotFound($this->container, $response, $primaryKey, $this->administratorsModel->getPrimaryTableModel(), $this->routePrefix);
        }

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        // if no changes made, redirect
        // note, if password field is blank, it will not be included in changed fields check
        // debatable whether this should be part of validation and stay on page with error

        $changedFields = $this->administratorsModel->getChangedColumnsValues(['name' => $input['name'], 'username' => $input['username'], 'role_id' => (int) $input['role_id'], 'password' => $input['password']], $record);

        if (count($changedFields) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made (Record $primaryKey)", App::STATUS_ADMIN_NOTICE_FAILURE];
            FormHelper::unsetFormSessionVars();
            return $response->withRedirect($this->router->pathFor($redirectRoute));
        }

        $this->setValidation($input, $record);

        if (!$this->validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($this->validator->getFirstErrors());
            return $this->view->updateView($request, $response, $args);
        }

        if (!$this->administratorsModel->getPrimaryTableModel()->updateRecordByPrimaryKey($changedFields, $primaryKey, false)) {
            throw new \Exception("Update Failure");
        }

        // if the administrator changed her/his own info, update the session
        if ($primaryKey == $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID]) {
            $this->updateAdministratorSession($changedFields);
        }

        $this->systemEvents->insertInfo("Updated ".$this->administratorsModel::TABLE_NAME, (int) $this->authentication->getAdministratorId(), "id:$primaryKey");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated record $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    /** update whatever has changed of name, username, role */
    private function updateAdministratorSession(array $changedFields)
    {
        foreach ($changedFields as $fieldName => $fieldValue) {
            if ($fieldName == 'name') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME] = $fieldValue;
            } elseif ($fieldName == 'username') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME] = $fieldValue;
            } elseif ($fieldName == 'role_id') {
                $rolesModel = new RolesModel();
                if (!$newRole = $rolesModel->getRoleForRoleId((int) $fieldValue)) {
                    throw new \Exception('Role not found for changed role id: '.$fieldValue);
                }
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES] = $newRole;
            }
        }
    }

    // override for custom validation and return column
    public function getDelete(Request $request, Response $response, $args)
    {
        // make sure the current admin is not deleting themself
        if ((int) ($args['primaryKey']) == $this->container->authentication->getAdministratorId()) {
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
