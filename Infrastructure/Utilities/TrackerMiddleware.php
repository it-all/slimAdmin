<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\Middleware;

class TrackerMiddleware extends Middleware
{
	public function __invoke(Request $request, Response $response, $next)
	{
        $this->container->events->insertInfo('Resource Requested');

		$response = $next($request, $response);
		return $response;
	}
}
