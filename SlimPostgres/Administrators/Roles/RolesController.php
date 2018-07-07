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
        } catch (Exceptions\InvalidArgumentException $e) {
            throw $e;
        } catch (Exceptions\UnallowedQueryException $e) {
            $this->systemEvents->insertWarning($e->getMessage(), (int) $this->authentication->getAdministratorId(), $eventNote);
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), 'adminNoticeFailure'];
            return false;
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->systemEvents->insertWarning($e->getMessage(), (int) $this->authentication->getAdministratorId(), $eventNote);
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), 'adminNoticeFailure'];
            return false;
        } catch (Exceptions\QueryFailureException $e) {
            $this->systemEvents->insertWarning($e->getMessage(), (int) $this->authentication->getAdministratorId(), $eventNote);
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), 'adminNoticeFailure'];
            return false;
        }

        parent::deleted($dbResult, $primaryKey, $returnColumn, $emailTo);
    }

    // can override for custom validator - example
    // public function postInsert(Request $request, Response $response, $args)
    // {
    //     if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
    //         throw new \Exception('No permission.');
    //     }

    //     $this->setRequestInput($request, $this->getBooleanFieldNames());

    //     $validator = new RolesValidator($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);

    //     if (!$validator->validate()) {
    //         // redisplay the form with input values and error(s)
    //         FormHelper::setFieldErrors($validator->getFirstErrors());
    //         return $this->view->insertView($request, $response, $args);
    //     }

    //     try {
    //         $this->insert();
    //     } catch (\Exception $e) {
    //         throw new \Exception("Insert failure. ".$e->getMessage());
    //     }

    //     FormHelper::unsetFormSessionVars();
    //     return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    // }
}
