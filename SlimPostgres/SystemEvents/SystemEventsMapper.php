<?php
declare(strict_types=1);

namespace SlimPostgres\SystemEvents;

use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\DataMappers\MultiTableMapper;

class SystemEventsMapper extends MultiTableMapper
{
    const PRIMARY_TABLE_NAME = 'system_events';
    const TYPES_TABLE_NAME = 'system_event_types';
    const ADMINISTRATORS_TABLE_NAME = 'administrators';

    // event types: debug, info, notice, warning, error, critical, alert, emergency [props to monolog]
    const SELECT_COLUMNS = [
        'id' => self::PRIMARY_TABLE_NAME . '.id',
        'created' => self::PRIMARY_TABLE_NAME . '.created',
        'event_type' => self::TYPES_TABLE_NAME . '.event_type',
        'event' => self::PRIMARY_TABLE_NAME . '.title',
        'name' => self::ADMINISTRATORS_TABLE_NAME . '.name',
        'notes' => self::PRIMARY_TABLE_NAME . '.notes',
        'ip_address' => self::PRIMARY_TABLE_NAME . '.ip_address',
        'request_method' => self::PRIMARY_TABLE_NAME . '.request_method',
        'resource' => self::PRIMARY_TABLE_NAME . '.resource'
    ];

    public function __construct()
    {
        // note time_stamp is the alias for created used in view query
        parent::__construct(new TableMapper(self::PRIMARY_TABLE_NAME, '*', 'time_stamp', false), self::SELECT_COLUMNS);
    }

    public function insertDebug(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'debug', $adminId, $notes);
    }

    public function insertInfo(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'info', $adminId, $notes);
    }

    public function insertNotice(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'notice', $adminId, $notes);
    }

    public function insertWarning(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'warning', $adminId, $notes);
    }

    public function insertError(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'error', $adminId, $notes);
    }

    public function insertCritical(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'critical', $adminId, $notes);
    }

    public function insertAlert(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'alert', $adminId, $notes);
    }

    public function insertEmergency(string $title, int $adminId = null, string $notes = null)
    {
        $this->insertEvent($title, 'emergency', $adminId, $notes);
    }

    public function insertEvent(string $title, string $eventType = 'info', int $adminId = null, string $notes = null)
    {
        if (!$eventTypeId = $this->getEventTypeId($eventType)) {
            throw new \Exception("Invalid eventType: $eventType");
        }

        $this->insert($title, (int) $eventTypeId, $notes, $adminId);
    }

    private function insert(string $title, int $eventType = 2, string $notes = null, int $adminId = null)
    {
        if (mb_strlen(trim($title)) == 0) {
            throw new \Exception("Title cannot be blank");
        }

        if ($notes !== null && mb_strlen(trim($notes)) == 0) {
            $notes = null;
        }

        // allow 0 to be passed in instead of null, convert to null so query won't fail
        if ($adminId == 0) {
            $adminId = null;
        }

        // query can fail if event_type or administrator_id fk not present.

        $q = new QueryBuilder("INSERT INTO ".self::PRIMARY_TABLE_NAME." (event_type, title, notes, administrator_id, ip_address, resource, request_method) VALUES($1, $2, $3, $4, $5, $6, $7)", $eventType, $title, $notes, $adminId, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

        try {
            $res = $q->execute();
        } catch (\Exception $e) {
            // suppress exception as it will result in infinite loop in error handler, which also calls this fn
            return false;
        }

        return $res;
    }

    public function getEventTypeId(string $eventType)
    {
        $q = new QueryBuilder("SELECT id FROM ".self::TYPES_TABLE_NAME." WHERE event_type = $1", $eventType);
        return $q->getOne();
    }

    public function select(string $columns = '*', array $filterColumnsInfo = null)
    {
        $selectClause = "SELECT $columns";
        $fromClause = "FROM ".self::PRIMARY_TABLE_NAME." JOIN ".self::TYPES_TABLE_NAME." ON ".self::PRIMARY_TABLE_NAME.".event_type = ".self::TYPES_TABLE_NAME.".id LEFT OUTER JOIN ".self::ADMINISTRATORS_TABLE_NAME." ON ".self::PRIMARY_TABLE_NAME.".administrator_id = ".self::ADMINISTRATORS_TABLE_NAME.".id";

        $orderByClause = "ORDER BY ".self::PRIMARY_TABLE_NAME.".created DESC";

        if ($filterColumnsInfo != null) {
            $this->validateFilterColumns($filterColumnsInfo);
        }

        $q = new SelectBuilder($selectClause, $fromClause, $filterColumnsInfo, $orderByClause);
        return $q->execute();
    }

    public function hasForAdmin(int $adminId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(*) FROM ".self::PRIMARY_TABLE_NAME." WHERE administrator_id = $1", $adminId);
        return (bool) $q->getOne();
    }
}
