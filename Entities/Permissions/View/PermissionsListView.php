<?php
declare(strict_types=1);

namespace Entities\Permissions\View;

use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Entities\Permissions\Model\PermissionsEntityMapper;
use Entities\Permissions\Model\PermissionsTableMapper;
use Exceptions\QueryFailureException;
use Psr\Container\ContainerInterface as Container;
use Entities\Permissions\Model\Permission;
use Infrastructure\Database\Postgres;

class PermissionsListView extends AdminFilterableListView
{
    use ResponseUtilities;

    private $permissionsEntityMapper;
    private $permissionsTableMapper;

    const FILTER_FIELDS_PREFIX = 'permissions';
    const INITIAL_SORT_COLUMN = 'title';

    public function __construct(Container $container)
    {
        $this->permissionsEntityMapper = PermissionsEntityMapper::getInstance();
        $this->permissionsTableMapper = PermissionsTableMapper::getInstance();
        $indexPath = $container->get('routeParser')->urlFor(ROUTE_ADMINISTRATORS_PERMISSIONS);
        $filterFieldName = ROUTEPREFIX_PERMISSIONS . 'Filter';
        $filterResetPath = $indexPath . '/reset';

        parent::__construct($container, $this->permissionsEntityMapper, $indexPath, self::INITIAL_SORT_COLUMN, ROUTEPREFIX_PERMISSIONS, $filterFieldName, $filterResetPath);
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return PERMISSIONS_INSERT_RESOURCE;
                break;
            case 'update':
                return PERMISSIONS_UPDATE_RESOURCE;
                break;
            case 'delete':
                return PERMISSIONS_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }
    
    // adds the delete header column
    protected function getHeaders(bool $hasResults = true): array 
    {
        $headers = [];

        $columnNames = ['id', 'title', 'description', 'roles', 'active', 'created'];
        $headerNames = $this->deletesAuthorized ? array_merge($columnNames, [self::DELETE_COLUMN_TEXT]) : $columnNames;
        foreach ($headerNames as $name) {
            $headers[] = [
                'class' => $this->getHeaderClass($name, $hasResults),
                'text' => $name,
            ];
        }

        return $headers;
    }

    private function getUpdateCellValue(int $primaryKeyValue): string 
    {
        $updatePath = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'update'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return $this->updatesAuthorized ? '<a href="'.$updatePath.'" title="update">'.$primaryKeyValue.'</a>' : $primaryKeyValue;
    }

    private function getDeleteCellValue(Permission $permission): string 
    {
        $primaryKeyValue = $permission->getId();
        $deletePath = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'delete'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return $this->deletesAuthorized && $permission->isDeletable() ? '<a href="'.$deletePath.'" title="delete" onclick="return confirm(\'Are you sure you want to delete Permission '.$primaryKeyValue.' '.$permission->getTitle().'?\');">X</a>' : '';
    }

    // adds the delete column, with a value if permission is deletable
    private function getListViewRow(Permission $permission): array 
    {
        return [
            'id' => $this->getUpdateCellValue($permission->getId()),
            'title' => $permission->getTitle(),
            'description' => $permission->getdescription(),
            'roles' => $permission->getRolesString(),
            'active' => Postgres::convertBoolToPostgresBool($permission->isActive()),
            'created' => $permission->getCreated()->format('Y-m-d'),
            self::DELETE_COLUMN_TEXT => $this->getDeleteCellValue($permission),
        ];
    }

    // returns array of table content for permissions
    protected function getListArray(): array
    {
        $list = [];

        $permissions = $this->permissionsEntityMapper->getObjects($this->getFilterColumnsInfo(), self::INITIAL_SORT_COLUMN);
        
        foreach ($permissions as $permission) {
            $list[] = $this->getListViewRow($permission);
        }

        return $list;
    }
}
