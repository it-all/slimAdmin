<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\Middleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class GuestMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // if administrator signed in redirect to admin home
        if ($this->container->get('authentication')->isAuthenticated()) {
            $routeParser = RouteContext::fromRequest($request)->getRouteParser();
            $response = new Response();
            return $response
                ->withHeader('Location', $routeParser->urlFor(ROUTE_ADMIN_HOME_DEFAULT))
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}