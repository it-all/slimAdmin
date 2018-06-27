<?php
declare(strict_types=1);

namespace SlimPostgres\Utilities;

use SlimPostgres\App;
use SlimPostgres\Database\DataMappers\TableMapper;
use Slim\Container;
use Slim\Http\Response;

trait ResponseUtilities
{
    // this can be used when a record is not found for the given primary key
    private function databaseRecordNotFound(Response $response, $primaryKey, TableMapper $mapper, string $routeAction)
    {
        // $routeAction must be 'update' or 'delete'
        if ($routeAction != 'update' && $routeAction != 'delete') {
            throw new \Exception("routeAction $routeAction must be update or delete");
        }
        $eventNote = $mapper->getPrimaryKeyColumnName().":$primaryKey|Table: ".$mapper->getTableName();
        $this->systemEvents->insertWarning("Record not found for $routeAction", (int) $this->authentication->getAdministratorId(), $eventNote);
        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Record $primaryKey Not Found", 'adminNoticeFailure'];
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }

}
