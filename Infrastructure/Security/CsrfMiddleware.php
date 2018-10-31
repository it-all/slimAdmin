<?php
declare(strict_types=1);

namespace Infrastructure\Security;

use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\Middleware;

class CsrfMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
        if (false === $request->getAttribute('csrf_status')) {
            $this->container->events->setAdministratorId($this->container->authentication->getAdministratorId());
            $this->container->events->insertSecurity(CSRF_FAULT);
            session_unset();
            $_SESSION[SESSION_NOTICE] = ['Error. Your session has been reset.', 'error'];
            return $response->withRedirect($this->container->router->pathFor(ROUTE_HOME));
        }

		$response = $next($request, $response);
		return $response;
	}
}
