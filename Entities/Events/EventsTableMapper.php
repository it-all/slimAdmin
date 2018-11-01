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

    private $administratorId;

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

    /** this must be called in order to set the currently logged in administrator */
    public function setAdministratorId(int $administratorId) 
    {
        $this->administratorId = $administratorId;
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

    public function insertDebug(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'debug', $notes);
    }

    public function insertInfo(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'info', $notes);
    }

    public function insertNotice(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'notice', $notes);
    }

    public function insertWarning(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'warning', $notes);
    }

    public function insertError(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'error', $notes);
    }

    public function insertSecurity(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'security', $notes);
    }

    public function insertCritical(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'critical', $notes);
    }

    public function insertAlert(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'alert', $notes);
    }

    public function insertEmergency(string $title, ?string $notes = null): ?int
    {
        return $this->insertEvent($title, 'emergency', $notes);
    }

    public function insertEvent(string $title, string $eventType = 'info', ?string $notes = null): ?int
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

        $sessionId = (session_id() == '') ? null : session_id();
        $referer = ($_SERVER['HTTP_REFERER'] == '') ? null : $_SERVER['HTTP_REFERER'];
        
        $columnValues = [
            'event_type_id' => $eventTypeId, 
            'title' => $title,
            'notes' => $notes,
            'created' => 'NOW()',
            'administrator_id' => $this->administratorId,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'resource' => $_SERVER['REQUEST_URI'],
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'referer' => $referer,
            'session_id' => $sessionId,
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
