<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimPostgres;
use Infrastructure\Middleware;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
		if (!$this->container->authentication->isAuthenticated()) {
			$this->container->events->insertWarning(EVENT_LOGIN_REQUIRED);
			SlimPostgres::setAdminNotice("Login required", 'failure');
            $_SESSION[SlimPostgres::SESSION_KEY_GOTO_ADMIN_PATH] = $request->getUri()->getPath();
            return $response->withRedirect($this->container->router->pathFor(ROUTE_LOGIN));
		}

		$response = $next($request, $response);
		return $response;
	}
}
