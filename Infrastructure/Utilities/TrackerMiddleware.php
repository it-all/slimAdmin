<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Infrastructure\Middleware;
use Psr\Http\Server\RequestHandlerInterface;

class TrackerMiddleware extends Middleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->container->get('events')->insertInfo('Resource Requested');
        return $handler->handle($request);
    }
}
