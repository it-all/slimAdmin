<?php
declare(strict_types=1);

namespace Entities\Permissions;

use Infrastructure\SlimAdmin;
use Entities\Roles\Model\RolesTableMapper;
use Entities\Permissions\Model\Permission;
use Entities\Permissions\Model\PermissionsTableMapper;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Entities\Permissions\Model\PermissionsValidator;
use Entities\Permissions\View\PermissionsListView;
use Entities\Permissions\View\Forms\PermissionForm;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Exceptions;
use Entities\Permissions\View\PermissionsInsertView;
use Entities\Permissions\View\PermissionsUpdateView;
use Infrastructure\BaseEntity\BaseMVC\Controller\ListViewAdminController;

class PermissionsController extends ListViewAdminController
{
    use ResponseUtilities;

    private $permissionsEntityMapper;
    private $permissionsTableMapper;
    private $routePrefix;
    private $changedFieldsString;

    public function __construct(Container $container)
    {
        $this->permissionsEntityMapper = PermissionsEntityMapper::getInstance();
        $this->permissionsTableMapper = PermissionsTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_PERMISSIONS;
        parent::__construct($container);
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $listView = new PermissionsListView($this->container);
        $this->setIndexFilter($request, $this->permissionsEntityMapper::SELECT_COLUMNS, $listView);
        return $listView->indexView($response);
    }

    /** note authorization check handled by middleware */
    public function routePostInsert(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request, PermissionForm::getFieldNames());
        $input = $this->requestInput;

        $validator = new PermissionsValidator($input);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $input;
            return (new PermissionsInsertView($this->container))->insertView($request, $response, $args);
        }

        try {
            $permissionId = $this->permissionsEntityMapper->create($input[PermissionForm::TITLE_FIELD_NAME], $input[PermissionForm::DESCRIPTION_FIELD_NAME], $input[PermissionForm::ROLES_FIELDSET_NAME], FormHelper::getBoolForCheckboxField($input[PermissionForm::ACTIVE_FIELD_NAME]));
        } catch (\Exception $e) {
            throw new \Exception("Permission create failure. ".$e->getMessage());
        }

        $eventPayload = [
            $this->permissionsTableMapper->getPrimaryKeyColumnName() => $permissionId,
            PermissionForm::TITLE_FIELD_NAME => $input[PermissionForm::TITLE_FIELD_NAME],
            PermissionForm::DESCRIPTION_FIELD_NAME => $input[PermissionForm::DESCRIPTION_FIELD_NAME],
            PermissionForm::ACTIVE_FIELD_NAME => $input[PermissionForm::ACTIVE_FIELD_NAME],
            PermissionForm::ROLES_FIELDSET_NAME => $input[PermissionForm::ROLES_FIELDSET_NAME],
        ];
        $this->events->insertInfo(EVENT_PERMISSION_INSERT, $eventPayload);

        SlimAdmin::addAdminNotice("Inserted Permission $permissionId");
        return $response
        ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_ADMINISTRATORS_PERMISSIONS))
        ->withStatus(302);
    }

    /** note authorization check handled by middleware */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        $primaryKey = $args[ROUTEARG_PRIMARY_KEY];

        // if all roles have been unchecked it won't be included in the post will be set null
        $this->setRequestInput($request, PermissionForm::getFieldNames());
        $input = $this->requestInput;

        $redirectRoute = SlimAdmin::getRouteName(true, $this->routePrefix,'index');

        // make sure there is a permission for the primary key
        if (null === $permission = $this->permissionsEntityMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->permissionsTableMapper, 'update');
        }

        // check for changes made
        // only check the password if it has been supplied (entered in the form)
        $changedFields = $this->getChangedFieldValues($permission, $input['title'], $input['description'], FormHelper::getBoolForCheckboxField($input['active']), $input['roles']);

        // if no changes made, display error message
        if (count($changedFields) == 0) {
            SlimAdmin::addAdminNotice("No changes made", 'failure');
            return (new PermissionsUpdateView($this->container))->updateView($request, $response, $args);
        }

        $validator = new PermissionsValidator($input, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $input;
            return (new PermissionsUpdateView($this->container))->updateView($request, $response, $args);
        }
        
        $this->permissionsEntityMapper->doUpdate((int) $primaryKey, $changedFields);

        $eventsPayload = array_merge([$this->permissionsTableMapper->getPrimaryKeyColumnName() => $primaryKey], $changedFields);
        $this->events->insertInfo(EVENT_PERMISSION_UPDATE, $eventsPayload);
        SlimAdmin::addAdminNotice("Updated permission $primaryKey");
        
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
            ->withStatus(302);
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        $primaryKey = (int) $args[ROUTEARG_PRIMARY_KEY];

        try {
            $title = $this->permissionsEntityMapper->delete($primaryKey);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, PermissionsTableMapper::getInstance(), 'delete', 'Permission');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->events->insertWarning(EVENT_UNALLOWED_ACTION, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice($e->getMessage(), 'failure');
            return $response
                ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
                ->withStatus(302);
        } catch (Exceptions\QueryFailureException $e) {
            $this->events->insertError(EVENT_PERMISSION_DELETE_FAIL, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice('Delete Failed', 'failure');
            return $response
                ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
                ->withStatus(302);
        }

        $eventPayload = [$this->permissionsTableMapper->getPrimaryKeyColumnName() => $primaryKey, 'title' => $title];
        $this->events->insertInfo(EVENT_PERMISSION_DELETE, $eventPayload);
        SlimAdmin::addAdminNotice("Deleted permission $primaryKey(title: $title)");

        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
            ->withStatus(302);
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
            if ($currentRoleId != (RolesTableMapper::getInstance())->getTopRoleId() && !in_array($currentRoleId, $roleIds)) {
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
}
