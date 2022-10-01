<?php
declare(strict_types=1);

namespace Entities\Administrators;

use Entities\Administrators\Model\AdministratorsValidator;
use Entities\Administrators\Model\Administrator;
use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Administrators\Model\AdministratorsTableMapper;
use Entities\Administrators\View\Forms\AdministratorForm;
use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Exceptions;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Entities\Administrators\View\AdministratorsListView;
use Entities\Administrators\View\AdministratorsInsertView;
use Entities\Administrators\View\AdministratorsUpdateView;
use Infrastructure\BaseEntity\BaseMVC\Controller\ListViewAdminController;

class AdministratorsController extends ListViewAdminController
{
    use ResponseUtilities;

    private $administratorsEntityMapper;
    private $administratorsTableMapper;
    private $routePrefix;
    private $changedFieldsString;

    public function __construct(Container $container)
    {
        $this->administratorsEntityMapper = AdministratorsEntityMapper::getInstance();
        $this->administratorsTableMapper = AdministratorsTableMapper::getInstance();
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        parent::__construct($container);
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $listView = new AdministratorsListView($this->container);
        $this->setIndexFilter($request, $this->administratorsEntityMapper::SELECT_COLUMNS, $listView);
        return $listView->indexView($response);
    }

    /** note authorization check handled by middleware */
    public function routePostInsert(Request $request, Response $response, $args)
    {
        $this->setRequestInput($request, AdministratorForm::getFieldNames());
        $input = $this->requestInput;

        $validator = new AdministratorsValidator($input, $this->authorization);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $input;
            return (new AdministratorsInsertView($this->container))->insertView($request, $response, $args);
        }

        $administratorId = $this->administratorsEntityMapper->create($input['name'], $input['username'], $input['password'], $input['roles'], FormHelper::getBoolForCheckboxField($input['active']));

        $eventPayload = [
            $this->administratorsTableMapper->getPrimaryKeyColumnName() => $administratorId,
            'name' => $input['name'],
            'username' => $input['username'],
            'active' => $input['active'],
            'roles' => $input['roles'],
        ];
        $this->events->insertInfo(EVENT_ADMINISTRATOR_INSERT, $eventPayload);

        SlimAdmin::addAdminNotice("Inserted administrator $administratorId");
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_ADMINISTRATORS))
            ->withStatus(302);
    }

    /** note authorization check handled by middleware */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        $primaryKey = $args[ROUTEARG_PRIMARY_KEY];

        // if all roles have been unchecked it won't be included in the post will be set null
        $this->setRequestInput($request, AdministratorForm::getFieldNames());
        $input = $this->requestInput;

        $redirectRoute = SlimAdmin::getRouteName(true, $this->routePrefix,'index');

        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->administratorsEntityMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsTableMapper, 'update');
        }

        // check for changes made
        // only check the password if it has been supplied (entered in the form)
        $changedFields = $this->getChangedFieldValues($administrator, $input['name'], $input['username'], $input['roles'], FormHelper::getBoolForCheckboxField($input['active']), mb_strlen($input['password']) > 0, $input['password']);

        // if no changes made, display error message
        if (count($changedFields) == 0) {
            SlimAdmin::addAdminNotice("No changes made", 'failure');
            return (new AdministratorsUpdateView($this->container))->updateView($request, $response, $args);
        }

        $validator = new AdministratorsValidator($input, $this->authorization, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimAdmin::USER_INPUT_KEY] = $input;
            return (new AdministratorsUpdateView($this->container))->updateView($request, $response, $args);
        }
        
        $this->administratorsEntityMapper->doUpdate((int) $primaryKey, $changedFields);

        $eventsPayload = array_merge([$this->administratorsTableMapper->getPrimaryKeyColumnName() => $primaryKey], $changedFields);
        $this->events->insertInfo(EVENT_ADMINISTRATOR_UPDATE, $eventsPayload);
        SlimAdmin::addAdminNotice("Updated administrator $primaryKey");
        
        return $response
        ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
        ->withStatus(302);
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        $primaryKey = (int) $args[ROUTEARG_PRIMARY_KEY];

        try {
            $username = $this->administratorsEntityMapper->delete($primaryKey, $this->authentication, $this->authorization);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsTableMapper, 'delete', 'Administrator');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->events->insertWarning(EVENT_UNALLOWED_ACTION, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice($e->getMessage(), 'failure');
            return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
            ->withStatus(302);
        } catch (Exceptions\QueryFailureException $e) {
            $this->events->insertError(EVENT_ADMINISTRATOR_DELETE_FAIL, ['error' => $e->getMessage()]);
            SlimAdmin::addAdminNotice('Delete Failed', 'failure');
            return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
            ->withStatus(302);
        }

        $eventPayload = [$this->administratorsTableMapper->getPrimaryKeyColumnName() => $primaryKey, 'username' => $username];
        $this->events->insertInfo(EVENT_ADMINISTRATOR_DELETE, $eventPayload);
        SlimAdmin::addAdminNotice("Deleted administrator $primaryKey(username: $username)");

        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix,'index')))
            ->withStatus(302);
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
}
