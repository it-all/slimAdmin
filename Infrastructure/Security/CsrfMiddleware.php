<?php
declare(strict_types=1);

namespace Infrastructure\Security;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Infrastructure\Middleware;
use Infrastructure\SlimAdmin;

class CsrfMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
        if (false === $request->getAttribute(CSRF_STATUS_ATTRIBUTE)) {
            $this->container->get('events')->setAdministratorId($this->container->get('authentication')->getAdministratorId());
            $this->container->get('events')->insertSecurity(CSRF_FAULT);
            session_unset();
            $_SESSION[SlimAdmin::SESSION_KEY_NOTICE] = ['Error. Your session has been reset.', 'error'];
            return $response
                ->withHeader('Location', $this->container->get('routeParser')->urlFor(ROUTE_HOME))
                ->withStatus(302);
        }

		$response = $next($request, $response);
		return $response;
	}
}
