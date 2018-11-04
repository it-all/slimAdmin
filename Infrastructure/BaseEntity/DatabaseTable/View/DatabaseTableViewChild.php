<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\Database\DataMappers\TableMapper;

// a list view class for a single database table
class DatabaseTableViewChild extends DatabaseTableView
{
    protected $routePrefix;
    protected $tableMapper;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndex(Request $request, Response $response, $args)
    {
        $this->tableMapper = new TableMapper($args[ROUTEARG_DATABASE_TABLE_NAME]);
        parent::__construct($this->container, $this->tableMapper, ROUTEPREFIX_ROLES);

        return $this->indexView($response);
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return ROLES_INSERT_RESOURCE;
                break;
            case 'update':
                return ROLES_UPDATE_RESOURCE;
                break;
            case 'delete':
                return ROLES_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }
}
