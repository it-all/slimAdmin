<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authentication;

use Infrastructure\SlimAdmin;
use Infrastructure\Middleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;
use Slim\Routing\RouteContext;

class AuthenticationMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->container->get('authentication')->isAuthenticated()) {
            $this->container->get('events')->insertWarning(EVENT_LOGIN_REQUIRED);
            SlimAdmin::addAdminNotice("Login required", 'failure');
            $routeContext = RouteContext::fromRequest($request);
            $routeParser = $routeContext->getRouteParser();
            $route = $routeContext->getRoute();
            $_SESSION[SlimAdmin::SESSION_KEY_GOTO_ADMIN_PATH] = $routeParser->urlFor($route->getName(), $route->getArguments());
            $response = new Response();
            return $response
                ->withHeader('Location', $routeParser->urlFor(ROUTE_LOGIN))
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
