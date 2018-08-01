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

    // override to check exceptions
    protected function delete($primaryKey, ?string $returnColumn = null, ?string $emailTo = null)
    {
        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey, $returnColumn);
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), App::STATUS_ADMIN_NOTICE_FAILURE];
            throw $e;
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning('Query Results Not Found', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), App::STATUS_ADMIN_NOTICE_FAILURE];
            throw $e;
        } catch (Exceptions\QueryFailureException $e) {
            $this->systemEvents->insertError('Query Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Delete Failed', App::STATUS_ADMIN_NOTICE_FAILURE];
            throw $e;
        }

        parent::deleted($dbResult, $primaryKey, $returnColumn, $emailTo);
    }
}
