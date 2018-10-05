<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions;

use SlimPostgres\App;
use SlimPostgres\BaseController;
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

        $this->setRequestInput($request, PermissionForm::getFields());
        $input = $this->requestInput;

        $validator = new PermissionsValidator($input);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $input;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $permissionId = $this->permissionsMapper->create($input['permission'], $input['description'], $input['roles'], FormHelper::getBoolForCheckboxField($input['active']));
        } catch (\Exception $e) {
            throw new \Exception("Permission create failure. ".$e->getMessage());
        }

        $this->systemEvents->insertInfo("Inserted Permission", (int) $this->authentication->getAdministratorId(), "id:$permissionId");

        App::setAdminNotice("Inserted Permission $administratorId");
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS_PERMISSIONS));
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
}
