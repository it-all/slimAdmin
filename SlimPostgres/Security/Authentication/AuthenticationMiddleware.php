<?php
declare(strict_types=1);

namespace SlimPostgres\Security\Authentication;

use SlimPostgres\App;
use SlimPostgres\BaseMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthenticationMiddleware extends BaseMiddleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
		if (!$this->container->authentication->isAuthenticated()) {
		    $this->container->systemEvents->insertWarning('Login Required');
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Login required", App::STATUS_ADMIN_NOTICE_FAILURE];
            $_SESSION[App::SESSION_KEY_GOTO_ADMIN_PATH] = $request->getUri()->getPath();
            return $response->withRedirect($this->container->router->pathFor(ROUTE_LOGIN));
		}

		$response = $next($request, $response);
		return $response;
	}
}
