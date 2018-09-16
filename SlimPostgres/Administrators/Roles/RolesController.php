<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\DatabaseTableController;
use SlimPostgres\Exceptions;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use SlimPostgres\Forms\FormHelper;

class RolesController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesMapper::getInstance(), new RolesView($container), ROUTEPREFIX_ROLES);
    }

    /** override to call objects view */
    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), $this->view);
        return $this->view->indexViewObjects($response);
    }

    // override to check exceptions
    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->mapper->getTableName(false);
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();

        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey, $returnColumn);
            $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName: $primaryKey");
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Deleted $tableName $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), App::STATUS_ADMIN_NOTICE_FAILURE];
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning('Query Results Not Found', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), App::STATUS_ADMIN_NOTICE_FAILURE];
        } catch (Exceptions\QueryFailureException $e) {
            $this->systemEvents->insertError('Query Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Delete Failed', App::STATUS_ADMIN_NOTICE_FAILURE];
        }

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }
}
