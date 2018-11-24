<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\Middleware;

/** redirects to home route for logged in administrator if necessary */
class AdministratorHomeRouteMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
		// if we're on the current home route for administrator return current page in order to avoid infinite redirect loop
		$route = $request->getAttribute('route');
		$routeName = $route->getName();
		$administratorHomeRoute = $this->container->authentication->getAdminHomeRouteForAdministrator();
		if ($routeName != $administratorHomeRoute) {
			return $response->withRedirect($this->container->router->pathFor($administratorHomeRoute));
		}
		$response = $next($request, $response);
		return $response;
	}
}
