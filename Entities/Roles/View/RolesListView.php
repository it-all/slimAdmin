<?php
declare(strict_types=1);

namespace Entities\Roles\View;

use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\SlimAdmin;
use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Psr\Container\ContainerInterface as Container;
use Entities\Roles\Model\Role;

class RolesListView extends AdminFilterableListView
{
    const INITIAL_SORT_COLUMN = 'id';

    public function __construct(Container $container)
    {
        $indexPath = $container->get('routeParser')->urlFor(ROUTE_ADMINISTRATORS_ROLES);
        $filterFieldName = ROUTEPREFIX_ROLES . 'Filter';
        $filterResetPath = $indexPath . '/reset';
        
        parent::__construct($container, RolesTableMapper::getInstance(), $indexPath, self::INITIAL_SORT_COLUMN, ROUTEPREFIX_ROLES, $filterFieldName, $filterResetPath);
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

    private function getUpdateCellValue(int $primaryKeyValue): string 
    {
        $updatePath = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'update'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return $this->updatesAuthorized ? '<a href="'.$updatePath.'" title="update">'.$primaryKeyValue.'</a>' : $primaryKeyValue;
    }

    private function getDeleteCellValue(Role $role): string 
    {
        $primaryKeyValue = $role->getId();
        $deletePath = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'delete'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return $this->deletesAuthorized && $role->isDeletable() ? '<a href="'.$deletePath.'" title="delete" onclick="return confirm(\'Are you sure you want to delete Role '.$primaryKeyValue.' '.$role->getRoleName().'?\');">X</a>' : '';
    }

    // adds the delete column, with a value if role is deletable
    private function getListViewRow(Role $role): array 
    {
        return [
            'id' => $this->getUpdateCellValue($role->getId()),
            'role' => $role->getRoleName(),
            'created' => $role->getCreated()->format('Y-m-d'),
            self::DELETE_COLUMN_TEXT => $this->getDeleteCellValue($role),
        ];
    }

    // returns array of table content for roles
    protected function getListArray(): array
    {
        $list = [];
        foreach ($this->mapper->getObjects($this->getFilterColumnsInfo(), self::INITIAL_SORT_COLUMN) as $role) {
            $list[] = $this->getListViewRow($role);
        }
        return $list;
    }
}
