<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\LoginAttempts\LoginAttemptsMapper;
use SlimPostgres\Administrators\Forms\AdministratorForm;
use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
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

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->administratorsMapper::SELECT_COLUMNS, $this->view);
        return $this->view->indexViewObjects($response);
    }

    public function routePostInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, AdministratorForm::getFieldNames());
        $input = $this->requestInput;

        $validator = new AdministratorsValidator($input, $this->authorization);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $input;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $administratorId = $this->administratorsMapper->create($input['name'], $input['username'], $input['password'], $input['roles'], FormHelper::getBoolForCheckboxField($input['active']));
        } catch (\Exception $e) {
            throw new \Exception("Administrator create failure. ".$e->getMessage());
        }

        $this->systemEvents->insertInfo("Inserted Administrator", (int) $this->authentication->getAdministratorId(), "id:$administratorId");

        App::setAdminNotice("Inserted administrator $administratorId");
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        // if all roles have been unchecked it won't be included in the post will be set null
        $this->setRequestInput($request, AdministratorForm::getFieldNames());
        $input = $this->requestInput;

        $redirectRoute = App::getRouteName(true, $this->routePrefix,'index');

        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->administratorsMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'update');
        }

        // check for changes made
        // only check the password if it has been supplied (entered in the form)
        $changedFields = $this->getChangedFieldValues($administrator, $input['name'], $input['username'], $input['roles'], FormHelper::getBoolForCheckboxField($input['active']), mb_strlen($input['password']) > 0, $input['password']);

        // if no changes made, display error message
        if (count($changedFields) == 0) {
            App::setAdminNotice("No changes made", 'failure');
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new AdministratorsValidator($input, $this->authorization, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $input;
            return $this->view->updateView($request, $response, $args);
        }
        
        $this->administratorsMapper->doUpdate((int) $primaryKey, $changedFields);

        // if the administrator changed her/his own info, refresh administrator then update the session
        if ((int) $primaryKey === $this->authentication->getAdministratorId()) {
            // refreshes $administrator to updated db values
            if (null !== $administrator = $this->administratorsMapper->getObjectById((int) $primaryKey)) {
                $this->authentication->updateAdministratorSession($administrator);
            } else {
                throw new \Exception("Get administrator object failed");
            }
        }

        $this->systemEvents->insertInfo("Updated Administrator", (int) $this->authentication->getAdministratorId(), "id:$primaryKey|".$this->getChangedFieldsString($administrator, $changedFields));
        App::setAdminNotice("Updated administrator $primaryKey");
        
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = (int) $args['primaryKey'];

        try {
            $username = $this->administratorsMapper->delete($primaryKey, $this->authentication, $this->authorization);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'delete', 'Administrator');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            App::setAdminNotice($e->getMessage(), 'failure');
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        } catch (\Exception $e) {
            $this->systemEvents->insertError('Administrator Deletion Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            App::setAdminNotice('Delete Failed', 'failure');
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        $eventNote = $this->administratorsMapper->getPrimaryTableMapper()->getPrimaryKeyColumnName() . ":$primaryKey|username: $username";
        $this->systemEvents->insertInfo("Deleted Administrator", (int) $this->authentication->getAdministratorId(), $eventNote);
        App::setAdminNotice("Deleted administrator $primaryKey(username: $username)");

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }

    private function getChangedFieldValues(Administrator $administrator, string $name, string $username, ?array $roleIds, bool $active, bool $includePassword = true, ?string $password = null): array 
    {
        $changedFieldValues = [];

        if ($administrator->getName() != $name) {
            $changedFieldValues[AdministratorForm::NAME_FIELD_NAME] = $name;
        }
        if ($administrator->getUsername() != $username) {
            $changedFieldValues[AdministratorForm::USERNAME_FIELD_NAME] = $username;
        }

        if ($includePassword && !password_verify($password, $administrator->getPasswordHash())) {
            $changedFieldValues[AdministratorForm::PASSWORD_FIELD_NAME] = $password;
        }

        if ($administrator->getActive() !== $active) {
            $changedFieldValues[AdministratorForm::ACTIVE_FIELD_NAME] = $active;
        }

        // roleIds - only add to main array if changed
        if ($roleIds === null) {
            $roleIds = [];
        }
        $addRoles = []; // populate with ids of new roles
        $removeRoles = []; // populate with ids of former roles
        
        // search roles to add
        $addRoles = [];
        foreach ($roleIds as $newRoleId) {
            if (!$administrator->hasRole((int) $newRoleId)) {
                $addRoles[] = $newRoleId;
            }
        }

        // search roles to remove
        $removeRoles = [];
        foreach ($administrator->getRoleIds() as $currentRoleId) {
            if (!in_array($currentRoleId, $roleIds)) {
                $removeRoles[] = $currentRoleId;
            }
        }
        
        if (count($addRoles) > 0) {
            $changedFieldValues[AdministratorForm::ROLES_FIELDSET_NAME]['add'] = $addRoles;
        }

        if (count($removeRoles) > 0) {
            $changedFieldValues[AdministratorForm::ROLES_FIELDSET_NAME]['remove'] = $removeRoles;
        }

        return $changedFieldValues;
    }

    private function getChangedFieldsString(Administrator $administrator, array $changedFields): string 
    {
        $allowedChangedFieldsKeys = array_merge([AdministratorForm::ROLES_FIELDSET_NAME], (AdministratorsMapper::getInstance()::ADMINISTRATORS_UPDATE_FIELDS));

        $changedString = "";

        foreach ($changedFields as $fieldName => $newValue) {

            // make sure only correct fields have been input
            if (!in_array($fieldName, $allowedChangedFieldsKeys)) {
                throw new \InvalidArgumentException("$fieldName not allowed in changedFields");
            }

            $oldValue = $administrator->{"get".ucfirst($fieldName)}();
            
            if ($fieldName == AdministratorForm::ROLES_FIELDSET_NAME) {

                $rolesMapper = RolesMapper::getInstance();

                $addRoleIds = (isset($newValue['add'])) ? $newValue['add'] : [];
                $removeRoleIds = (isset($newValue['remove'])) ? $newValue['remove'] : [];

                // update values based on add/remove and old roles
                $updatedNewValue = "";
                $updatedOldValue = "";
                foreach ($oldValue as $role) {
                    $updatedOldValue .= $role->getRoleName()." ";
                    // don't put the roles being removed into the new value
                    if (!in_array($role->getId(), $removeRoleIds)) {
                        $updatedNewValue .= $role->getRoleName()." ";
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


}
