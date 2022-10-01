<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\BaseMVC\View;

use Infrastructure\SlimAdmin;
use Infrastructure\Database\DataMappers\TableMapper;
use Psr\Http\Message\ResponseInterface as Response;

trait ResponseUtilities
{
    // this can be used when a record is not found for the given primary key for update and delete attempts
    private function databaseRecordNotFound(Response $response, $primaryKey, TableMapper $tableMapper, string $routeAction, ?string $title = null)
    {
        // $routeAction must be 'update' or 'delete'
        if ($routeAction != 'update' && $routeAction != 'delete') {
            throw new \Exception("routeAction $routeAction must be update or delete");
        }

        // enter event
        $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, [$tableMapper->getPrimaryKeyColumnName() => $primaryKey, 'table' => $tableMapper->getTableName()]);

        $noticeTitle = ($title != null) ? $title: 'Record';
        SlimAdmin::addAdminNotice("$noticeTitle $primaryKey Not Found", 'failure');
        return $response
            ->withHeader('Location', $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'index')))
            ->withStatus(302);
    }
}
