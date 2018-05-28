<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\Security\Authentication;

use It_All\Slim_Postgres\Infrastructure\Middleware;

class GuestMiddleware extends Middleware
{
    public function __invoke($request, $response, $next)
    {
        // if user signed in redirect to admin home
        if ($this->container->authentication->check()) {
            return $response->withRedirect($this->container->router->pathFor(ROUTE_ADMIN_HOME_DEFAULT));
        }

        $response = $next($request, $response);
        return $response;
    }
}