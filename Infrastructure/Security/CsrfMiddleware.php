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
            $eventTitle = 'CSRF Check Failure';
            $this->container->systemEvents->insertError($eventTitle, (int) $this->container->authentication->getAdministratorId());
            session_unset();
            $_SESSION[SESSION_NOTICE] = ['Error. Your session has been reset.', 'error'];
            return $response->withRedirect($this->container->router->pathFor(ROUTE_HOME));
        }

//        $this->container->view->getEnvironment()->addGlobal('csrf', [
//            'tokenNameKey' => $this->container->csrf->getTokenNameKey(),
//            'tokenName' => $this->container->csrf->getTokenName(),
//            'tokenValueKey' => $this->container->csrf->getTokenValueKey(),
//            'tokenValue' => $this->container->csrf->getTokenValue()
//        ]);

		$response = $next($request, $response);
		return $response;
	}
}
