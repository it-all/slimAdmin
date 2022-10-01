<?php
declare(strict_types=1);

namespace Entities\Events;

use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\SlimAdmin;
use Infrastructure\Utilities\ErrorHandler;

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
    public function setAdministratorId(?int $administratorId) 
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

    public function insertDebug(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'debug', $payload);
    }

    public function insertInfo(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'info', $payload);
    }

    public function insertNotice(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'notice', $payload);
    }

    public function insertWarning(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'warning', $payload);
    }

    public function insertError(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'error', $payload);
    }

    public function insertSecurity(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'security', $payload);
    }

    public function insertCritical(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'critical', $payload);
    }

    public function insertAlert(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'alert', $payload);
    }

    public function insertEmergency(string $title, ?array $payload = null): ?int
    {
        return $this->insertEvent($title, 'emergency', $payload);
    }

    public function insertEvent(string $title, string $eventType = 'info', ?array $payload = null): ?int
    {
        if (null === $eventTypeId = $this->getEventTypeId($eventType)) {
            throw new \Exception("Invalid eventType: $eventType");
        }

        if (mb_strlen(trim($title)) == 0) {
            throw new \Exception("Title cannot be blank");
        }

        if ($payload !== null) {
            if (count($payload) == 0) {
                $payload = null;
            } else {
                if (!$payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK)) {
                    throw new \InvalidArgumentException("Payload is invalid JSON");
                }
            }
        }

        $sessionId = (session_id() == '') ? null : session_id();
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        /** these three aren't defined for cli scripts */
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        $columnValues = [
            'event_type_id' => $eventTypeId, 
            'title' => $title,
            'payload' => $payload,
            'created' => 'NOW()',
            'administrator_id' => $this->administratorId,
            'ip_address' => $ipAddress,
            'resource' => $requestUri,
            'request_method' => $requestMethod,
            'referer' => $referer,
            'session_id' => $sessionId,
        ];

        /** log error but suppress exception as it could result in infinite loop because error handler also calls this fn */
        /** note that echoing and emailing of the error are not done */
        try {
            return (int) parent::insert($columnValues);
        } catch (\Exception $e) {
            ErrorHandler::logError($e->getMessage());
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
        if (null === $row = $q->getRow()) {
            return false;
        }
        return (bool) $row[0];
    
    }
}
