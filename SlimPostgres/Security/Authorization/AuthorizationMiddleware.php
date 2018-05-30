<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authorization;

use SlimPostgres\App;
use SlimPostgres\Middleware;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/* The permissions to check is either the minimum allowable role for the resource or an array of allowable roles */
class AuthorizationMiddleware extends Middleware
{
    private $permissions;

    public function __construct(Container $container, $permissions)
    {
        $this->permissions = $permissions;
        parent::__construct($container);
    }

    public function __invoke(Request $request, Response $response, $next)
	{
        if (!$this->container->authorization->check($this->permissions)) {
            $this->container->systemEvents->insertAlert('No authorization for resource', $this->container->authentication->getUserId());

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['No permission', 'adminNoticeFailure'];

            return $response->withRedirect($this->container->router->pathFor(ROUTE_ADMIN_HOME_DEFAULT));
        }

		$response = $next($request, $response);
		return $response;
	}
}
