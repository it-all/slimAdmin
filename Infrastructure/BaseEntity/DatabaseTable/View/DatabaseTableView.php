<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class DatabaseTableView
{
    private $tableName;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function routeIndex(Request $request, Response $response, $args)
    {
        $this->tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($this->tableName)) {
            throw new \Exception("Database table does not exist: ".$this->tableName);
        }

        return (new DatabaseTableListView($this->container, $this->tableName))->indexView($response);
    }

    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        $this->tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($this->tableName)) {
            throw new \Exception("Database table does not exist: ".$this->tableName);
        }

        return (new DatabaseTableListView($this->container, $this->tableName))->resetFilter($response);
    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        $this->tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($this->tableName)) {
            throw new \Exception("Database table does not exist: ".$this->tableName);
        }

        return (new DatabaseTableInsertView($this->container, $this->tableName))->insertView($request, $response, $args);
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        $this->tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        if (!$this->container->get('postgres')->doesTableExist($this->tableName)) {
            throw new \Exception("Database table does not exist: ".$this->tableName);
        }

        return (new DatabaseTableUpdateView($this->container, $this->tableName, (int) $args[ROUTEARG_PRIMARY_KEY]))->updateView($request, $response, $args);
    }
    
}
