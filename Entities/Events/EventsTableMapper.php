<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\Database\Queries\QueryBuilder;

// fake Singleton with public constructor
final class EventsTableMapper extends TableMapper
{
    /** @var array of event_types records: id => [eventy_type, description]. Populated at construction in order to reduce future queries */
    private $eventTypes;

    const TABLE_NAME = 'events';
    const TYPES_TABLE_NAME = 'event_types';

    public static function getInstance()
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new EventsTableMapper();
        }
        return $instance;
    }

    public function __construct()
    {
        parent::__construct(self::TABLE_NAME, '*', 'created', false);
        $this->setEventTypes();
    }

    private function setEventTypes()
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

    public function insertDebug(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'debug', $administratorId, $notes);
    }

    public function insertInfo(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'info', $administratorId, $notes);
    }

    public function insertNotice(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'notice', $administratorId, $notes);
    }

    public function insertWarning(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'warning', $administratorId, $notes);
    }

    public function insertError(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'error', $administratorId, $notes);
    }

    public function insertSecurity(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'security', $administratorId, $notes);
    }

    public function insertCritical(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'critical', $administratorId, $notes);
    }

    public function insertAlert(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'alert', $administratorId, $notes);
    }

    public function insertEmergency(string $title, ?int $administratorId = null, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'emergency', $administratorId, $notes);
    }

    public function insertEvent(string $title, string $eventType = 'info', ?int $administratorId = null, ?string $notes = null): ?int
    {
        if (null === $eventTypeId = $this->getEventTypeId($eventType)) {
            throw new \Exception("Invalid eventType: $eventType");
        }

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
       
        $columnValues = [
            'event_type_id' => $eventTypeId, 
            'title' => $title,
            'notes' => $notes,
            'created' => 'NOW()',
            'administrator_id' => $administratorId,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'resource' => $_SERVER['REQUEST_URI'],
            'request_method' => $_SERVER['REQUEST_METHOD']
        ];

        /** suppress exception as it will result in infinite loop in error handler, which also calls this fn */
        try {
            return (int) parent::insert($columnValues);
        } catch (\Exception $e) {
            /** may want to log error here since it's being squelched, but it's not easy to get the error log path here or even the ErrorHandler object */
            return null;
        }
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

    public function existForAdministrator(int $administratorId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(*) FROM ".self::TABLE_NAME." WHERE administrator_id = $1", $administratorId);
        return (bool) $q->getOne();
    }
}
