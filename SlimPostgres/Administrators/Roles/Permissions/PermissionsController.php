<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\App;
use SlimPostgres\BaseController;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\Roles\Permissions\Model\Permission;
use SlimPostgres\Administrators\Roles\Permissions\Model\PermissionsMapper;
use SlimPostgres\Administrators\Roles\Permissions\Model\PermissionsValidator;
use SlimPostgres\Administrators\Roles\Permissions\View\PermissionsViews;
use SlimPostgres\Administrators\Roles\Permissions\View\Forms\PermissionForm;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class PermissionsController extends BaseController
{
    use ResponseUtilities;

    private $permissionsMapper;
    private $view;
    private $routePrefix;
    private $changedFieldsString;

    public function __construct(Container $container)
    {
        $this->permissionsMapper = PermissionsMapper::getInstance();
        $this->view = new PermissionsViews($container);
        $this->routePrefix = ROUTEPREFIX_PERMISSIONS;
        parent::__construct($container);
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->permissionsMapper::SELECT_COLUMNS, $this->view);
        return $this->view->indexViewObjects($response);
    }

    public function routePostInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, PermissionForm::getFieldNames());
        $input = $this->requestInput;

        $validator = new PermissionsValidator($input);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $input;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $permissionId = $this->permissionsMapper->create($input[PermissionForm::TITLE_FIELD_NAME], $input[PermissionForm::DESCRIPTION_FIELD_NAME], $input[PermissionForm::ROLES_FIELDSET_NAME], FormHelper::getBoolForCheckboxField($input[PermissionForm::ACTIVE_FIELD_NAME]));
        } catch (\Exception $e) {
            throw new \Exception("Permission create failure. ".$e->getMessage());
        }

        $this->systemEvents->insertInfo("Inserted Permission", (int) $this->authentication->getAdministratorId(), "id:$permissionId");

        App::setAdminNotice("Inserted Permission $administratorId");
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS_PERMISSIONS));
    }

    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        // if all roles have been unchecked it won't be included in the post will be set null
        $this->setRequestInput($request, PermissionForm::getFieldNames());
        $input = $this->requestInput;

        $redirectRoute = App::getRouteName(true, $this->routePrefix,'index');

        // make sure there is a permission for the primary key
        if (null === $permission = $this->permissionsMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->permissionsMapper->getPrimaryTableMapper(), 'update');
        }

        // check for changes made
        // only check the password if it has been supplied (entered in the form)
        $changedFields = $this->getChangedFieldValues($permission, $input['title'], $input['description'], FormHelper::getBoolForCheckboxField($input['active']), $input['roles']);

        // if no changes made, display error message
        if (count($changedFields) == 0) {
            App::setAdminNotice("No changes made", 'failure');
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new PermissionsValidator($input, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $input;
            return $this->view->updateView($request, $response, $args);
        }
        
        $this->permissionsMapper->doUpdate((int) $primaryKey, $changedFields);

        $this->systemEvents->insertInfo("Updated Permission", (int) $this->authentication->getAdministratorId(), "id:$primaryKey|".$this->getChangedFieldsString($permission, $changedFields));
        App::setAdminNotice("Updated permission $primaryKey");
        
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = (int) $args['primaryKey'];

        try {
            $permission = $this->permissionsMapper->delete($primaryKey);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->permissionsMapper->getPrimaryTableMapper(), 'delete', 'Permission');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            App::setAdminNotice($e->getMessage(), 'failure');
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        } catch (\Exception $e) {
            $this->systemEvents->insertError('Permission Deletion Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            App::setAdminNotice('Delete Failed', 'failure');
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        $eventNote = $this->permissionsMapper->getPrimaryTableMapper()->getPrimaryKeyColumnName() . ":$primaryKey|username: $username";
        $this->systemEvents->insertInfo("Deleted Permission", (int) $this->authentication->getAdministratorId(), $eventNote);
        App::setAdminNotice("Deleted permission $primaryKey($permission)");

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }

    private function getChangedFieldValues(Permission $permission, string $title, ?string $description, bool $active, ?array $roleIds): array 
    {
        $changedFieldValues = [];

        if ($permission->getTitle() != $title) {
            $changedFieldValues[PermissionForm::TITLE_FIELD_NAME] = $title;
        }
        if ($permission->getDescription() != $description) {
            $changedFieldValues[PermissionForm::DESCRIPTION_FIELD_NAME] = $description;
        }

        if ($permission->getActive() !== $active) {
            $changedFieldValues[PermissionForm::ACTIVE_FIELD_NAME] = $active;
        }

        /** if all roles are unchecked the $rolesId parameter will be null */
        if ($roleIds === null) {
            $roleIds = [];
        }

        // search roles to add
        $addRoles = [];
        foreach ($roleIds as $newRoleId) {
            if (!$permission->hasRole((int) $newRoleId)) {
                $addRoles[] = $newRoleId;
            }
        }

        // search roles to remove (ignore top role)
        $removeRoles = [];
        foreach ($permission->getRoleIds() as $currentRoleId) {
            if ($currentRoleId != (RolesMapper::getInstance())->getTopRoleId() && !in_array($currentRoleId, $roleIds)) {
                $removeRoles[] = $currentRoleId;
            }
        }

        if (count($addRoles) > 0) {
            $changedFieldValues[PermissionForm::ROLES_FIELDSET_NAME]['add'] = $addRoles;
        }

        if (count($removeRoles) > 0) {
            $changedFieldValues[PermissionForm::ROLES_FIELDSET_NAME]['remove'] = $removeRoles;
        }

        return $changedFieldValues;
    }

    private function getChangedFieldsString(Permission $permission, array $changedFields): string 
    {
        $allowedChangedFieldsKeys = array_merge([PermissionForm::ROLES_FIELDSET_NAME], (PermissionsMapper::getInstance()::PERMISSIONS_UPDATE_FIELDS));

        $changedString = "";

        foreach ($changedFields as $fieldName => $newValue) {

            // make sure only correct fields have been input
            if (!in_array($fieldName, $allowedChangedFieldsKeys)) {
                throw new \InvalidArgumentException("$fieldName not allowed in changedFields");
            }

            $oldValue = $permission->{"get".ucfirst($fieldName)}();
            
            if ($fieldName == PermissionForm::ROLES_FIELDSET_NAME) {

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
