<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\App;
use SlimPostgres\DatabaseTableController;
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

    // can override for custom validator
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

    // override to check condition and add custom return column
    protected function delete($primaryKey, string $returnColumn = null, bool $sendEmail = false)
    {
        // make sure role is not being used
        if ($this->mapper::hasAdministrator((int) $primaryKey)) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Role in use", App::STATUS_ADMIN_NOTICE_FAILURE];
            return false;
        }

        parent::delete($primaryKey, 'role', $sendEmail);
    }
}
