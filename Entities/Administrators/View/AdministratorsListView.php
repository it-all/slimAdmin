<?php
declare(strict_types=1);

namespace Entities\Administrators\View;

use Exceptions\QueryFailureException;
use Entities\Administrators\Model\AdministratorsEntityMapper;
use Entities\Administrators\Model\AdministratorsTableMapper;
use Infrastructure\SlimAdmin;
use Infrastructure\Database\Postgres;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Psr\Container\ContainerInterface as Container;
use Entities\Administrators\Model\Administrator;

class AdministratorsListView extends AdminFilterableListView
{
    use ResponseUtilities;

    private $administratorsEntityMapper;
    private $administratorsTableMapper;

    const FILTER_FIELDS_PREFIX = 'administrators';
    const INITIAL_SORT_COLUMN = 'name';

    public function __construct(Container $container)
    {
        $this->administratorsEntityMapper = AdministratorsEntityMapper::getInstance();
        $this->administratorsTableMapper = AdministratorsTableMapper::getInstance();
        $indexPath = $container->get('routeParser')->urlFor(ROUTE_ADMINISTRATORS);
        $filterFieldName = ROUTEPREFIX_ADMINISTRATORS . 'Filter';
        $filterResetPath = $indexPath . '/reset';

        parent::__construct($container, $this->administratorsEntityMapper, $indexPath, self::INITIAL_SORT_COLUMN, ROUTEPREFIX_ADMINISTRATORS, $filterFieldName, $filterResetPath);
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return ADMINISTRATORS_INSERT_RESOURCE;
                break;
            case 'update':
                return ADMINISTRATORS_UPDATE_RESOURCE;
                break;
            case 'delete':
                return ADMINISTRATORS_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }

    // adds the delete header column
    protected function getHeaders(bool $hasResults = true): array 
    {
        $headers = [];

        $columnNames = ['id', 'name', 'username', 'roles', 'active', 'created'];
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

    private function getDeleteCellValue(Administrator $administrator): string 
    {
        $primaryKeyValue = $administrator->getId();
        $deletePath = $this->container->get('routeParser')->urlFor(SlimAdmin::getRouteName(true, $this->routePrefix, 'delete'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return $this->deletesAuthorized && $administrator->isDeletable() ? '<a href="'.$deletePath.'" title="delete" onclick="return confirm(\'Are you sure you want to delete Administrator '.$primaryKeyValue.' '.htmlentities($administrator->getName(), ENT_QUOTES | ENT_HTML5).'?\');">X</a>' : '';
    }

    // adds the delete column, with a value if administrator is deletable
    private function getListViewRow(Administrator $administrator): array 
    {
        return [
            'id' => $this->getUpdateCellValue($administrator->getId()),
            'name' => $administrator->getName(),
            'username' => $administrator->getUsername(),
            'roles' => $administrator->getRolesString(),
            'active' => Postgres::convertBoolToPostgresBool($administrator->isActive()),
            'created' => $administrator->getCreated()->format('Y-m-d'),
            self::DELETE_COLUMN_TEXT => $this->getDeleteCellValue($administrator),
        ];
    }

    // returns array of table content for administrators
    protected function getListArray(): array
    {
        $list = [];

        $orderBy = null; // use entity mapper default
        $administrators = $this->administratorsEntityMapper->getObjects($this->getFilterColumnsInfo(), $orderBy, $this->authentication, $this->authorization);
        
        foreach ($administrators as $administrator) {
            $list[] = $this->getListViewRow($administrator);
        }

        return $list;
    }

}
