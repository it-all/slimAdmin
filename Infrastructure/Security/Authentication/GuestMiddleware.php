<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\Middleware;
use Slim\Http\Request;
use Slim\Http\Response;

class GuestMiddleware extends Middleware
{
    public function __invoke(Request $request, Response $response, $next)
    {
        // if administrator signed in redirect to admin home
        if ($this->container->authentication->isAuthenticated()) {
            return $response->withRedirect($this->container->router->pathFor(ROUTE_ADMIN_HOME_DEFAULT));
        }

        $response = $next($request, $response);
        return $response;
    }
}