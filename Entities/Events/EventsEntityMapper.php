<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\Database\DataMappers\EntityMapper;

// Singleton
final class EventsEntityMapper extends EntityMapper
{
    private $eventsTableMapper;

    const TABLE_NAME = 'events';
    const TYPES_TABLE_NAME = 'event_types';
    const ADMINISTRATORS_TABLE_NAME = 'administrators';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'created' => self::TABLE_NAME . '.created',
        'event_type' => self::TYPES_TABLE_NAME . '.event_type',
        'event' => self::TABLE_NAME . '.title',
        'name' => self::ADMINISTRATORS_TABLE_NAME . '.name AS administrator',
        'payload' => self::TABLE_NAME . '.payload',
        'notes' => self::TABLE_NAME . '.notes',
        'ip_address' => self::TABLE_NAME . '.ip_address',
        'request_method' => self::TABLE_NAME . '.request_method',
        'resource' => self::TABLE_NAME . '.resource',
        'referer' => self::TABLE_NAME . '.referer',
        'session' => self::TABLE_NAME . '.session_id',
    ];

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new EventsEntityMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        $this->setDefaultSelectColumnsString(self::SELECT_COLUMNS);
        $this->eventsTableMapper = EventsTableMapper::getInstance();
    }

    protected function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." JOIN ".self::TYPES_TABLE_NAME." ON ".self::TABLE_NAME.".event_type_id = ".self::TYPES_TABLE_NAME.".id LEFT OUTER JOIN ".self::ADMINISTRATORS_TABLE_NAME." ON ".self::TABLE_NAME.".administrator_id = ".self::ADMINISTRATORS_TABLE_NAME.".id";
    }

    protected function getOrderBy(): string 
    {
        return self::TABLE_NAME.".created DESC";
    }

    public function getListViewTitle(): string
    {
        return $this->eventsTableMapper->getListViewTitle();
    }

    public function getInsertTitle(): string
    {
        return $this->eventsTableMapper->getInsertTitle();
    }

    public function getUpdateColumnName(): ?string
    {
        return null;
    }

    public function getCountSelectColumns(): int
    {
        return count(self::SELECT_COLUMNS);
    }

    public function getListViewSortColumn(): ?string
    {
        return $this->eventsTableMapper->getListViewSortColumn();
    }

    public function getListViewSortAscending(): ?bool
    {
        return $this->eventsTableMapper->getListViewSortAscending();
    }
}
