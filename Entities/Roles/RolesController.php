<?php
declare(strict_types=1);

namespace Entities\Roles;

use Entities\Roles\Model\RolesMapper;
use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\Controller\DatabaseTableController;
use Exceptions;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\BaseMVC\View\Forms\FormHelper;

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
        if (!$this->authorization->isAuthorized(ROLES_DELETE_RESOURCE)) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->mapper->getFormalTableName(false);
        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();

        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey);
            $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            SlimPostgres::setAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning('Query Results Not Found', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            SlimPostgres::setAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            $this->systemEvents->insertError('Query Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            SlimPostgres::setAdminNotice('Delete Failed', 'failure');
        }

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }
}
