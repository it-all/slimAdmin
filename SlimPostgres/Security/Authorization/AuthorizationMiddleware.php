<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authorization;

use Domain\AdminHomeView;
use SlimPostgres\App;
use SlimPostgres\BaseMiddleware;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/* The permissions to check is either the minimum allowable role for the resource or an array of allowable roles */
class AuthorizationMiddleware extends BaseMiddleware
{
    private $permissions;

    public function __construct(Container $container, $permissions)
    {
        $this->permissions = $permissions;
        parent::__construct($container);
    }

    public function __invoke(Request $request, Response $response, $next)
	{
        if (!$this->container->authorization->isAuthorized($this->permissions)) {
            $this->container->systemEvents->insertAlert('No authorization for resource', $this->container->authentication->getAdministratorId());
		    App::setAdminNotice('No permission', 'failure');
            return (new AdminHomeView($this->container))->routeIndex($request, $response, ['status' => 403]);
        }

		$response = $next($request, $response);
		return $response;
	}
}
