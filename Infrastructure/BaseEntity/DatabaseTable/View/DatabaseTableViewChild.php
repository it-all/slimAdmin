<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\Database\DataMappers\TableMapper;

// a view class for a single database table, where the table name is an arg of the route functions and is then injected to the parent class. This allows table CRUD without creating table models.
class DatabaseTableViewChild extends DatabaseTableView
{
    protected $routePrefix;
    protected $tableMapper;
    private $tableName;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndex(Request $request, Response $response, $args)
    {
        $this->tableName = $args[ROUTEARG_DATABASE_TABLE_NAME];
        $this->tableMapper = new TableMapper($this->tableName);
        parent::__construct($this->container, $this->tableMapper, ROUTEPREFIX_ROLES);

        return $this->indexView($response);
    }

    protected function setInsert()
    {
        return null;
        $this->insertLinkInfo = null;
        /** note, no separate authorization for insert/update/delete. if administrator is authorized for crud s/he is good to go */
        // if ()
        $this->insertLinkInfo = ($this->authorization->isAuthorized($this->getResource('insert'))) ? [
            'text' => $this->mapper->getInsertTitle(), 
            'route' => SlimPostgres::getRouteName(true, $this->routePrefix, 'insert')
        ] : null;
    }

    protected function setUpdate()
    {
        /** can be null */
        $this->updateColumn = null;

        $this->updatesPermitted = false;

        $this->updateRoute = null;
    }

    protected function setDelete()
    {
        $this->deletesPermitted = false;

        $this->deleteRoute = null;
    }

    // protected function getResource(string $which): string 
    // {
    //     switch ($which) {
    //         case 'insert':
    //             return ROLES_INSERT_RESOURCE;
    //             break;
    //         case 'update':
    //             return ROLES_UPDATE_RESOURCE;
    //             break;
    //         case 'delete':
    //             return ROLES_DELETE_RESOURCE;
    //             break;
    //         default:
    //             throw new \InvalidArgumentException("Undefined resource $which");
    //     }
    // }
}
