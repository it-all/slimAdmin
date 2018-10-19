<?php
declare(strict_types=1);

namespace Entities\SystemEvents;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Database\DataMappers\MultiTableMapper;
use Infrastructure\Database\Postgres;
use Infrastructure\Functions;

// Singleton
final class SystemEventsMapper extends MultiTableMapper
{
    /** @var array of system_event_types records: id => [eventy_type, description]. Populated at construction in order to reduce future queries */
    private $eventTypes;

    const PRIMARY_TABLE_NAME = 'system_events';
    const TYPES_TABLE_NAME = 'system_event_types';
    const ADMINISTRATORS_TABLE_NAME = 'administrators';

    // event types: debug, info, notice, warning, error, critical, alert, emergency [props to monolog]
    const SELECT_COLUMNS = [
        'id' => self::PRIMARY_TABLE_NAME . '.id',
        'created' => self::PRIMARY_TABLE_NAME . '.created',
        'event_type' => self::TYPES_TABLE_NAME . '.event_type',
        'event' => self::PRIMARY_TABLE_NAME . '.title',
        'name' => self::ADMINISTRATORS_TABLE_NAME . '.name AS administrator',
        'notes' => self::PRIMARY_TABLE_NAME . '.notes',
        'ip_address' => self::PRIMARY_TABLE_NAME . '.ip_address',
        'request_method' => self::PRIMARY_TABLE_NAME . '.request_method',
        'resource' => self::PRIMARY_TABLE_NAME . '.resource'
    ];

    const ORDER_BY_COLUMN_NAME = 'created';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new SystemEventsMapper();
        }
        return $instance;
    }

    protected function __construct()
    {
        $this->setEventTypes();

        // note time_stamp is the alias for created used in view query
        parent::__construct(new TableMapper(self::PRIMARY_TABLE_NAME, '*', self::ORDER_BY_COLUMN_NAME, false), self::SELECT_COLUMNS, self::ORDER_BY_COLUMN_NAME);
    }

    public function setEventTypes()
    {
        $this->eventTypes = [];

        $q = new QueryBuilder("SELECT * FROM ".self::TYPES_TABLE_NAME." ORDER BY id");
        $results = $q->execute();
        while ($record = pg_fetch_assoc($results)) {
            $this->eventTypes[$record['id']] = [
                'eventType' => $record['event_type'],
                'description' => $record['description']
            ];
        }
    }

    public function insertDebug(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'debug', $administratorId, $notes);
    }

    public function insertInfo(string $title, ?int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'info', $administratorId, $notes);
    }

    public function insertNotice(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'notice', $administratorId, $notes);
    }

    public function insertWarning(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'warning', $administratorId, $notes);
    }

    public function insertError(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'error', $administratorId, $notes);
    }

    public function insertCritical(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'critical', $administratorId, $notes);
    }

    public function insertAlert(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'alert', $administratorId, $notes);
    }

    public function insertEmergency(string $title, int $administratorId = null, string $notes = null)
    {
        $this->insertEvent($title, 'emergency', $administratorId, $notes);
    }

    public function insertEvent(string $title, string $eventType = 'info', ?int $administratorId = null, string $notes = null)
    {
        if (null === $eventTypeId = $this->getEventTypeId($eventType)) {
            throw new \Exception("Invalid eventType: $eventType");
        }

        $this->insert($title, (int) $eventTypeId, $notes, $administratorId);
    }

    private function insert(string $title, int $eventType = 2, string $notes = null, ?int $administratorId = null)
    {
        if (mb_strlen(trim($title)) == 0) {
            throw new \Exception("Title cannot be blank");
        }

        if ($notes !== null && mb_strlen(trim($notes)) == 0) {
            $notes = null;
        }

        // allow 0 to be passed in instead of null, convert to null so query won't fail
        if ($administratorId == 0) {
            $administratorId = null;
        }

        // query can fail if event_type or administrator_id fk not present.

        $q = new QueryBuilder("INSERT INTO ".self::PRIMARY_TABLE_NAME." (event_type, title, notes, administrator_id, ip_address, resource, request_method) VALUES($1, $2, $3, $4, $5, $6, $7)", $eventType, $title, $notes, $administratorId, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        
        try {
            $res = $q->execute();
        } catch (\Exception $e) {
            // suppress exception as it will result in infinite loop in error handler, which also calls this fn
            return;
        }

        return $res;
    }

    public function getEventTypeId(string $eventType): ?int
    {
        foreach ($this->eventTypes as $eventTypeId => $eventTypeData) {
            if ($eventTypeData['eventType'] == $eventType) {
                return (int) $eventTypeId;
            }
        }

        return null;
    }

    protected function getFromClause(): string 
    {
        return "FROM ".self::PRIMARY_TABLE_NAME." JOIN ".self::TYPES_TABLE_NAME." ON ".self::PRIMARY_TABLE_NAME.".event_type = ".self::TYPES_TABLE_NAME.".id LEFT OUTER JOIN ".self::ADMINISTRATORS_TABLE_NAME." ON ".self::PRIMARY_TABLE_NAME.".administrator_id = ".self::ADMINISTRATORS_TABLE_NAME.".id";
    }

    protected function getOrderBy(): string 
    {
        return self::PRIMARY_TABLE_NAME.".created DESC";
    }

    public function existForAdministrator(int $administratorId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(*) FROM ".self::PRIMARY_TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        return (bool) $q->getOne();
    }
}
