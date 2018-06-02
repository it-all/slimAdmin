<?php
declare(strict_types=1);

namespace SlimPostgres\Database\SingleTable;

use SlimPostgres\App;
use SlimPostgres\Database\SingleTable\SingleTableModel;
use Slim\Container;
use Slim\Http\Response;

class SingleTableHelper
{
    public static function updateRecordNotFound(Container $container, Response $response, $primaryKey, SingleTableModel $model, string $routePrefix)
    {
        $eventNote = $model->getPrimaryKeyColumnName().":$primaryKey|Table: ".$model->getTableName();
        $container->systemEvents->insertWarning('Record not found for update', (int) $container->authentication->getUserId(), $eventNote);
        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Record $primaryKey Not Found", 'adminNoticeFailure'];
        return $response->withRedirect($container->router->pathFor(App::getRouteName(true, $routePrefix, 'index')));
    }

}
