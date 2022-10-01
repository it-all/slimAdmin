<?php
declare(strict_types=1);

namespace Entities\Events;

use Entities\Events\EventsEntityMapper;
use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Psr\Container\ContainerInterface as Container;

class EventsListView extends AdminFilterableListView
{
    const LIMIT_VIEW_RESULTS = 500;
    const INITIAL_SORT_COLUMN = 'id';
    const INITIAL_SORT_DIRECTION = 'DESC';

    public function __construct(Container $container)
    {
        $indexPath = $container->get('routeParser')->urlFor(ROUTE_EVENTS);
        $filterFieldName = ROUTEPREFIX_EVENTS . 'Filter';
        $filterResetPath = $indexPath . '/reset';
        
        parent::__construct($container, EventsEntityMapper::getInstance(), $indexPath, self::INITIAL_SORT_COLUMN, ROUTEPREFIX_EVENTS, $filterFieldName, $filterResetPath, self::INITIAL_SORT_DIRECTION);
    }

    protected function getResource(string $which): string 
    {
        return '';
    }

    protected function setInsertsPermitted()
    {
        $this->insertsAuthorized = false;
    }

    protected function setUpdatesPermitted()
    {
        $this->updatesAuthorized = false;
    }

    protected function setDeletesPermitted()
    {
        $this->deletesAuthorized = false;
    }

    protected function getHeaders(bool $hasResults = true): array 
    {
        $headers = [];
        foreach ($this->mapper::SELECT_COLUMNS as $name => $tableSql) {
            $headers[] = [
                'class' => $this->getHeaderClass($name, $hasResults),
                'text' => $name,
            ];
        }

        return $headers;
    }

    // returns array of table content for roles
    protected function getListArray(): array
    {
        $list = [];
        if (null !== $events = $this->mapper->select(null, $this->getFilterColumnsInfo(), self::INITIAL_SORT_COLUMN . ' ' . self::INITIAL_SORT_DIRECTION, self::LIMIT_VIEW_RESULTS)) {
            foreach ($events as $event) {
                $list[] = $event;
            }    
        }
        return $list;
    }
}
