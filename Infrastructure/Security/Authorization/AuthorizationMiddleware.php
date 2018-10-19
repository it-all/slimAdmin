<?php
declare(strict_types=1);

namespace Infrastructure\Security\Authorization;

use Domain\AdminHomeView;
use Infrastructure\SlimPostgres;
use Infrastructure\Middleware;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

/* The permissions to check is either the minimum allowable role for the resource or an array of allowable roles */
class AuthorizationMiddleware extends Middleware
{
    private $resource;

    public function __construct(Container $container, string $resource)
    {
        $this->resource = $resource;
        parent::__construct($container);
    }

    public function __invoke(Request $request, Response $response, $next)
	{
        if (!$this->container->authorization->isAuthorized($this->resource)) {
            $this->container->systemEvents->insertAlert('No authorization for resource', $this->container->authentication->getAdministratorId());
		    SlimPostgres::setAdminNotice('No permission', 'failure');
            return (new AdminHomeView($this->container))->routeIndex($request, $response, ['status' => 403]);
        }

		$response = $next($request, $response);
		return $response;
	}
}
