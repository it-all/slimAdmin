<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Infrastructure\Middleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

/** redirects to home route for logged in administrator if necessary */
class AdministratorHomeRouteMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // if we're on the current home route for administrator return current page in order to avoid infinite redirect loop
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();
        $route = $routeContext->getRoute();
        $routeName = $route->getName();
        $administratorHomeRoute = $this->container->get('authentication')->getAdminHomeRouteForAdministrator();
        if ($routeName != $administratorHomeRoute) {
            $response = new Response();
            return $response
                ->withHeader('Location', $routeParser->urlFor($administratorHomeRoute))
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
