<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authorization;

use Domain\AdminHomeView;
use Infrastructure\SlimAdmin;
use Infrastructure\Middleware;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/* The permissions to check is either the minimum allowable role for the resource or an array of allowable roles */
class AuthorizationMiddleware extends Middleware
{
    private $resource;

    public function __construct(Container $container, string $resource)
    {
        $this->resource = $resource;
        parent::__construct($container);
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
        if (!$this->container->get('authorization')->isAuthorized($this->resource)) {
            $this->container->get('events')->insertSecurity(EVENT_UNAUTHORIZED_ACCESS_ATTEMPT, ['administrator id' => $this->container->get('authentication')->getAdministratorId()]);
		    SlimAdmin::addAdminNotice('No permission', 'failure');
            $response = new Response();
            return (new AdminHomeView($this->container))->routeIndex($request, $response, ['status' => 403]);
        }
        return $handler->handle($request);
	}
}
