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
        return $response->withRedirect($this->container->router->pathFor($this->container->authentication->getAdminHomeRouteForAdministrator()));
	}
}
