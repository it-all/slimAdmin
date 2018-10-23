<?php
declare(strict_types=1);

namespace Entities\SystemEvents;

use Infrastructure\Database\DataMappers\EntityMapper;

// Singleton
final class SystemEventsEntityMapper extends EntityMapper
{
    const TABLE_NAME = 'system_events';
    const TYPES_TABLE_NAME = 'system_event_types';
    const ADMINISTRATORS_TABLE_NAME = 'administrators';

    const SELECT_COLUMNS = [
        'id' => self::TABLE_NAME . '.id',
        'created' => self::TABLE_NAME . '.created',
        'event_type' => self::TYPES_TABLE_NAME . '.event_type',
        'event' => self::TABLE_NAME . '.title',
        'name' => self::ADMINISTRATORS_TABLE_NAME . '.name AS administrator',
        'notes' => self::TABLE_NAME . '.notes',
        'ip_address' => self::TABLE_NAME . '.ip_address',
        'request_method' => self::TABLE_NAME . '.request_method',
        'resource' => self::TABLE_NAME . '.resource'
    ];

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SystemEventsEntityMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        $this->setDefaultSelectColumnsString(self::SELECT_COLUMNS);
        $this->setEventTypes();
    }

    protected function getFromClause(): string 
    {
        return "FROM ".self::TABLE_NAME." JOIN ".self::TYPES_TABLE_NAME." ON ".self::TABLE_NAME.".event_type = ".self::TYPES_TABLE_NAME.".id LEFT OUTER JOIN ".self::ADMINISTRATORS_TABLE_NAME." ON ".self::TABLE_NAME.".administrator_id = ".self::ADMINISTRATORS_TABLE_NAME.".id";
    }

    protected function getOrderBy(): string 
    {
        return self::TABLE_NAME.".created DESC";
    }
}
