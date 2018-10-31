<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\View;

use Infrastructure\SlimPostgres;
use Infrastructure\Database\DataMappers\TableMapper;
use Slim\Container;
use Slim\Http\Response;

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
        $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, $tableMapper->getPrimaryKeyColumnName().":$primaryKey|Table: ".$tableMapper->getTableName());

        $noticeTitle = ($title != null) ? $title: 'Record';
        SlimPostgres::setAdminNotice("$noticeTitle $primaryKey Not Found", 'failure');
        
        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }
}
