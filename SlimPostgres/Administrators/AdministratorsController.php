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
use SlimPostgres\Exceptions;
use SlimPostgres\Utilities\Functions;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsController extends BaseController
{
    use ResponseUtilities;

    private $administratorsMapper;
    private $view;
    private $routePrefix;
    private $changedFieldsString;

    public function __construct(Container $container)
    {
        $this->administratorsMapper = AdministratorsMapper::getInstance();
        $this->view = new AdministratorsView($container);
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        parent::__construct($container);
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

        try {
            $administratorId = $this->administratorsMapper->create($input['name'], $input['username'], $input['password'], $input['roles']);
        } catch (\Exception $e) {
            throw new \Exception("Administrator create failure. ".$e->getMessage());
        }

        $this->systemEvents->insertInfo("Inserted administrator", (int) $this->authentication->getAdministratorId(), "id:$administratorId");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted administrator $administratorId", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    /** also sets changedFieldsString property */
    private function getChangedFieldValues(Administrator $administrator, array $input): array 
    {
        $changedFieldValues = [];
        $this->changedFieldsString = "";

        // if all roles have been unchecked it won't be included in user input
        if (!isset($input['roles'])) {
            $input['roles'] = [];
        }

        if ($administrator->getName() != $input['name']) {
            $changedFieldValues['name'] = $input['name'];
            $this->changedFieldsString .= "name: ".$administrator->getName()." => ".$input['name'];
        }
        if ($administrator->getUsername() != $input['username']) {
            $changedFieldValues['username'] = $input['username'];
        }

        // only check the password if it has been supplied (entered in the form)
        if (mb_strlen($input['password']) > 0 && !password_verify($input['password'], $administrator->getPasswordHash())) {
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

    public function getUpdateChangedFieldsString(array $changedFields, Administrator $administrator): string 
    {
        $changedString = "";
        foreach ($changedFields as $fieldName => $newValue) {
            $oldValue = $administrator->{"get".ucfirst($fieldName)}();
            
            if ($fieldName == 'roles') {

                $rolesMapper = RolesMapper::getInstance();

                $addRoleIds = (isset($newValue['add'])) ? $newValue['add'] : [];
                $removeRoleIds = (isset($newValue['remove'])) ? $newValue['remove'] : [];

                // update values based on add/remove and old roles
                $updatedNewValue = "";
                $updatedOldValue = "";
                foreach ($oldValue as $roleId => $roleInfo) {
                    $updatedOldValue .= $roleInfo['roleName']." ";
                    // don't put the roles being removed into the new value
                    if (!in_array($roleId, $removeRoleIds)) {
                        $updatedNewValue .= $roleInfo['roleName']." ";
                    }
                }
                foreach ($addRoleIds as $roleId) {
                    $updatedNewValue .= $rolesMapper->getRoleForRoleId((int) $roleId) . " ";
                }
                $newValue = $updatedNewValue;
                $oldValue = $updatedOldValue;
            }

            $changedString .= " $fieldName: $oldValue => $newValue, ";
        }

        return substr($changedString, 0, strlen($changedString)-2);
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

        // if no changes made, display error message
        // note, if password field is blank, it will not be included in changed fields check

        $changedFields = $this->getChangedFieldValues($administrator, $input);

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
        
        $this->administratorsMapper->update((int) $primaryKey, $changedFields);

        // if the administrator changed her/his own info, update the session
        if ($primaryKey == $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID]) {
            $this->updateAdministratorSession($changedFields);
        }

        $this->systemEvents->insertInfo("Updated administrator", (int) $this->authentication->getAdministratorId(), "id:$primaryKey|".$this->getUpdateChangedFieldsString($changedFields, $administrator));

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated administrator $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
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

        $primaryKey = (int) $args['primaryKey'];

        try {
            $username = $this->administratorsMapper->delete($primaryKey, $this->container->authentication, $this->container->systemEvents);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'delete', 'Administrator');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), 'adminNoticeFailure'];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        } catch (\Exception $e) {
            $this->systemEvents->insertError('Administrator Deletion Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Deletion Failure', 'adminNoticeFailure'];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        $eventNote = $this->administratorsMapper->getPrimaryTableMapper()->getPrimaryKeyColumnName() . ":$primaryKey|username: $username";
        $adminMessage = "Deleted administrator $primaryKey(username: $username)";

        $this->systemEvents->insertInfo("Deleted administrator", (int) $this->authentication->getAdministratorId(), $eventNote);
        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
                
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }
}
