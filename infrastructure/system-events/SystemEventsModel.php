<?php
declare(strict_types=1);

namespace It_All\Slim_Postgres\Infrastructure\System_Events;

use It_All\Slim_Postgres\Infrastructure\Database\Single_Table\SingleTableModel;
use It_All\Slim_Postgres\Infrastructure\Database\Queries\QueryBuilder;
use It_All\Slim_Postgres\Infrastructure\Database\Queries\SelectBuilder;
use It_All\Slim_Postgres\Infrastructure\Database\Multi_Table\MultiTableModel;

class SystemEventsModel extends MultiTableModel
{
    const TABLE_NAME = 'system_events';

    // event types: debug, info, notice, warning, error, critical, alert, emergency [props to monolog]
    const SELECT_COLUMNS = [
        'id' => 'se.id',
        'time_stamp' => 'se.created',
        'type' => 'syet.event_type',
        'event' => 'se.title',
        'admin' => 'administrators.name',
        'notes' => 'se.notes',
        'ip_address' => 'se.ip_address',
        'request_method' => 'se.request_method',
        'resource' => 'se.resource'
    ];

    public function __construct()
    {
        // note time_stamp is the alias for created used in view query
        parent::__construct(new SingleTableModel(self::TABLE_NAME, '*','time_stamp', false), self::SELECT_COLUMNS);
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
        if (strlen(trim($title)) == 0) {
            throw new \Exception("Title cannot be blank");
        }

        if ($notes !== null && strlen(trim($notes)) == 0) {
            $notes = null;
        }

        // allow 0 to be passed in instead of null, convert to null so query won't fail
        if ($adminId == 0) {
            $adminId = null;
        }

        // query can fail if event_type or admin_id fk not present.

        $q = new QueryBuilder("INSERT INTO system_events (event_type, title, notes, admin_id, ip_address, resource, request_method) VALUES($1, $2, $3, $4, $5, $6, $7)", $eventType, $title, $notes, $adminId, $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

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
        $q = new QueryBuilder("SELECT id FROM system_event_types WHERE event_type = $1", $eventType);
        return $q->getOne();
    }

    public function select(array $filterColumnsInfo = null)
    {
        $selectClause = "SELECT ";
        $columnCount = 0;
        foreach (self::SELECT_COLUMNS as $columnAlias => $columnNameSql) {
            $selectClause .= " $columnNameSql as $columnAlias";
            $columnCount++;
            if ($columnCount < count(self::SELECT_COLUMNS)) {
                $selectClause .= ",";
            }
        }

        $fromClause = "FROM system_events se JOIN system_event_types syet ON se.event_type = syet.id LEFT OUTER JOIN administrators ON se.admin_id = administrators.id";

        $orderByClause = "ORDER BY se.created DESC";

        if ($filterColumnsInfo != null) {
            $this->validateFilterColumns($filterColumnsInfo);
        }

        $q = new SelectBuilder($selectClause, $fromClause, $filterColumnsInfo, $orderByClause);
        return $q->execute();
    }

    public function hasForAdmin(int $adminId): bool
    {
        $q = new QueryBuilder("SELECT COUNT(*) FROM system_events WHERE admin_id = $1", $adminId);
        return (bool) $q->getOne();
    }
}
