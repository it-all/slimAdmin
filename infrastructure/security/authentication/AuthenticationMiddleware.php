<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Security\Authentication;

use It_All\Slim_Postgres\Infrastructure\Middleware;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
		// check if the user is not signed in
		if (!$this->container->authentication->check()) {
            $this->container->systemEvents->insertWarning('Login required to access resource');
            $_SESSION[SESSION_ADMIN_NOTICE] = ["Login required", 'adminNoticeFailure'];
            $_SESSION[SESSION_GOTO_ADMIN_PATH] = $request->getUri()->getPath();
            return $response->withRedirect($this->container->router->pathFor(ROUTE_LOGIN));
		}

		$response = $next($request, $response);
		return $response;
	}
}
