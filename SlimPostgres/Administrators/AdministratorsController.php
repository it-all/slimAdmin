<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\Logins\LoginAttemptsMapper;
use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
use SlimPostgres\DatabaseTableController;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsController extends BaseController
{
    use ResponseUtilities;

    private $administratorsMapper;
    private $view;
    private $routePrefix;
    private $administratorsDatabaseTableController;

    public function __construct(Container $container)
    {
        $this->administratorsMapper = AdministratorsMapper::getInstance();
        $this->view = new AdministratorsView($container);
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        $this->administratorsDatabaseTableController = new DatabaseTableController($container, $this->administratorsMapper->getPrimaryTableMapper(), $this->view, $this->routePrefix);
        parent::__construct($container);
    }

    // if this is for an update there must be changed fields
    private function setValidation(array $input, array $changedFieldValues = [])
    {
        $this->validator = $this->validator->withData($input);

        // bool - either inserting or !inserting (updating)
        $inserting = count($changedFieldValues) == 0;

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
        if ($inserting || array_key_exists('username', $changedFieldValues)) {
            $this->validator->rule('unique', 'username', $this->administratorsMapper->getPrimaryTableMapper()->getColumnByName('username'), $this->validator);
        }

        // all selected roles must be in roles table
        $this->validator->rule('array', 'roles');
        $rolesMapper = RolesMapper::getInstance();
        $this->validator->rule('in', 'roles.*', array_keys($rolesMapper->getRoles())); // role ids
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->administratorsMapper::SELECT_COLUMNS, ROUTE_ADMINISTRATORS, $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request); // no boolean fields to add

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        $validator = new AdministratorsValidator($input);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->getInsert($request, $response, $args);
        }

        if (!$administratorId = $this->administratorsMapper->create($input['name'], $input['username'], $input['password'], $input['roles'])) {
            throw new \Exception("Insert Failure");
        }

        $this->systemEvents->insertInfo("Inserted admin", (int) $this->authentication->getAdministratorId(), "id:$administratorId");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted record $administratorId", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    private function getChangedFieldValues(Administrator $administrator): array 
    {
        $changedFieldValues = [];

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        // if all roles have been unchecked it won't be included in user input
        if (!isset($input['roles'])) {
            $input['roles'] = [];
        }

        if ($administrator->getName() != $input['name']) {
            $changedFieldValues['name'] = $input['name'];
        }
        if ($administrator->getUsername() != $input['username']) {
            $changedFieldValues['username'] = $input['username'];
        }
        if (mb_strlen($input['password']) > 0 && $administrator->getPasswordHash() != $this->$administratorsDatabaseTableController->getHashedPassword($input['password'])) {
            $changedFieldValues['password'] = $input['password'];
        }

        // roles - only add to main array if changed
        $addRoles = []; // populate with ids of new roles
        $removeRoles = []; // populate with ids of former roles
        
        $currentRoles = $administrator->getRoles();

        // search roles to add
        foreach ($input['roles'] as $newRoleId) {
            if (!array_key_exists($newRoleId, $currentRoles)) {
                $addRoles[] = $newRoleId;
            }
        }

        // search roles to remove
        foreach ($currentRoles as $currentRoleId => $currentRoleInfo) {
            if (!in_array($currentRoleId, $input['roles'])) {
                $removeRoles[] = $currentRoleId;
            }
        }

        if (count($addRoles) > 0) {
            $changedFieldValues['roles']['add'] = $addRoles;
        }

        if (count($removeRoles) > 0) {
            $changedFieldValues['roles']['remove'] = $removeRoles;
        }

        return $changedFieldValues;
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

        // make sure there is an administrator for the primary key
        if (!$administrator = $this->administratorsMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'update');
        }

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        // if no changes made, redirect
        // note, if password field is blank, it will not be included in changed fields check
        // debatable whether this should be part of validation and stay on page with error

        $changedFields = $this->getChangedFieldValues($administrator);

        if (count($changedFields) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made", 'adminNoticeFailure'];
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new AdministratorsValidator($input, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->updateView($request, $response, $args);
        }
        
        // $this->setValidation($input, $changedFields);

        // if (!$this->validator->validate()) {
        //     // redisplay the form with input values and error(s)
        //     FormHelper::setFieldErrors($this->validator->getFirstErrors());
        //     return $this->view->updateView($request, $response, $args);
        // }

        $this->administratorsMapper->update((int) $primaryKey, $changedFields);

        // if the administrator changed her/his own info, update the session
        if ($primaryKey == $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID]) {
            $this->updateAdministratorSession($changedFields);
        }

        $this->systemEvents->insertInfo("Updated ".$this->administratorsMapper::TABLE_NAME, (int) $this->authentication->getAdministratorId(), "id:$primaryKey");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated record $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    /** update whatever has changed of name, username, roles if the currently logged on administrator has changed own info */
    private function updateAdministratorSession(array $changedFields)
    {
        foreach ($changedFields as $fieldName => $fieldValue) {
            if ($fieldName == 'name') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME] = $fieldValue;
            } elseif ($fieldName == 'username') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME] = $fieldValue;
            } elseif ($fieldName == 'role_id') {
                $rolesMapper = RolesMapper::getInstance();
                if (!$newRole = $rolesMapper->getRoleForRoleId((int) $fieldValue)) {
                    throw new \Exception('Role not found for changed role id: '.$fieldValue);
                }
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES] = $newRole;
            }
        }
    }

    // override for custom validation and return column
    public function getDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        // make sure there is an administrator for the primary key
        if (!$administrator = $this->administratorsMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'delete');
        }

        // make sure the current administrator is not deleting themself
        if ((int) $primaryKey == $this->container->authentication->getAdministratorId()) {
            throw new \Exception('You cannot delete yourself from administrators');
        }

        // make sure there are no system events for administrator being deleted
        if ($this->container->systemEvents->hasForAdmin((int) $primaryKey)) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["System events exist for administrator id $primaryKey", App::STATUS_ADMIN_NOTICE_FAILURE];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        // make sure there are no login attempts for administrator being deleted
        $loginsMapper = LoginAttemptsMapper::getInstance();
        if ($loginsMapper->hasAdministrator((int) $primaryKey)) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Login attempts exist for administrator id $primaryKey", App::STATUS_ADMIN_NOTICE_FAILURE];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        $this->administratorsMapper->delete((int) $primaryKey);

        // the event and notification code below is duplication from DatabaseTableController -> look to DRY
        $eventNote = "$primaryKeyColumnName:$primaryKey|username:" . $administrator->getUsername();
        $adminMessage = "Deleted record $primaryKey(username:" . $administrator->getUsername() . ")";

        $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), $eventNote);
        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
        
        unset($administrator);
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    
    }
}
