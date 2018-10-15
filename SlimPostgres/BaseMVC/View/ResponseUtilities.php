<?php
declare(strict_types=1);

namespace SlimPostgres\BaseMVC\View;

use SlimPostgres\App;
use SlimPostgres\Database\DataMappers\TableMapper;
use Slim\Container;
use Slim\Http\Response;

trait ResponseUtilities
{
    // this can be used when a record is not found for the given primary key for update and delete attempts
    private function databaseRecordNotFound(Response $response, $primaryKey, TableMapper $mapper, string $routeAction, ?string $title = null)
    {
        // $routeAction must be 'update' or 'delete'
        if ($routeAction != 'update' && $routeAction != 'delete') {
            throw new \Exception("routeAction $routeAction must be update or delete");
        }

        // enter system event
        $this->systemEvents->insertWarning("Query Results Not Found", (int) $this->authentication->getAdministratorId(), $mapper->getPrimaryKeyColumnName().":$primaryKey|Table: ".$mapper->getTableName());

        $noticeTitle = ($title != null) ? $title: 'Record';
        App::setAdminNotice("$noticeTitle $primaryKey Not Found", 'failure');
        
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }
}
