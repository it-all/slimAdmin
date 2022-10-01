<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Infrastructure\Database\DataMappers\TableMapper;
use Psr\Container\ContainerInterface as Container;
use Infrastructure\BaseEntity\BaseMVC\View\AdminFilterableListView;
use Exceptions\QueryFailureException;

class DatabaseTableListView extends AdminFilterableListView
{
    private $errorMessage;
    private $tableName;
    private $limitSelectRows;
    const ORDER_BY_DIRECTION = 'DESC';
    private $orderByColumn;

    public function __construct(Container $container, string $tableName)
    {
        $this->errorMessage = null;
        $this->tableName = $tableName;
        $tableMapper = new TableMapper($this->tableName);
        $this->orderByColumn = $tableMapper->getPrimaryKeyColumnName();
        // $this->orderByColumn = ($tableMapper->getPrimaryKeyColumnName()) ? $tableMapper->getPrimaryKeyColumnName() : '*';
        // $this->orderByColumn = ($tableMapper->getPrimaryKeyColumnName()) ? $tableMapper->getPrimaryKeyColumnName() : 'added_on';

        $indexPath = $container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName]);
        $sessionFilterFieldKey = $this->tableName . 'Filter';
        $filterResetPath = $container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_RESET, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName]); 
        parent::__construct($container, $tableMapper, $indexPath, $this->orderByColumn, ROUTEPREFIX_DATABASE_TABLES, $sessionFilterFieldKey, $filterResetPath, self::ORDER_BY_DIRECTION);
        $this->limitSelectRows = $this->settings['orm']['tables'][$this->tableName]['limitResults'] ?? $this->settings['orm']['limitResultsDefault'];
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return DATABASE_TABLES_INSERT_RESOURCE;
                break;
            case 'update':
                return DATABASE_TABLES_UPDATE_RESOURCE;
                break;
            case 'delete':
                return DATABASE_TABLES_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }

    /** override to use orm config logically anded with parent authorization check*/
    protected function setInsertsPermitted()
    {
        parent::setInsertsPermitted();
        $ormSettingsInsertsPermitted = $this->settings['orm']['tables'][$this->tableName]['allowInserts'] ?? $this->settings['orm']['allowInsertsDefault'] ?? false;
        $this->insertsAuthorized = $this->insertsAuthorized && $ormSettingsInsertsPermitted;
    }

    /** override to use orm config logically anded with parent authorization check*/
    protected function setUpdatesPermitted()
    {
        parent::setUpdatesPermitted();
        $ormSettingsUpdatesPermitted = $this->settings['orm']['tables'][$this->tableName]['allowUpdates'] ?? $this->settings['orm']['allowUpdatesDefault'] ?? false;
        $this->updatesAuthorized = $this->updatesAuthorized && $ormSettingsUpdatesPermitted;
    }

    /** override to use orm config logically anded with parent authorization check*/
    protected function setDeletesPermitted()
    {
        parent::setDeletesPermitted();
        $ormSettingsDeletesPermitted = $this->settings['orm']['tables'][$this->tableName]['allowDeletes'] ?? $this->settings['orm']['allowDeletesDefault'] ?? false;
        $this->deletesAuthorized = $this->deletesAuthorized && $ormSettingsDeletesPermitted;
    }

    protected function getInsertLinkInfo()
    {
        return $this->insertsAuthorized ? [
            'text' => $this->mapper->getInsertTitle(), 
            'href' => $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_INSERT, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName]),
        ] : null;
    }   

    private function getDeleteCellValue($primaryKeyValue): string 
    {
        $deletePath = $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_DELETE, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName, ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return '<a href="'.$deletePath.'" title="delete" onclick="return confirm(\'Are you sure you want to delete '.$primaryKeyValue.'?\');">'.self::DELETE_COLUMN_TEXT.'</a>';
    }

    private function getUpdateCellValue($primaryKeyValue): string 
    {
        $updatePath = $this->container->get('routeParser')->urlFor(ROUTE_DATABASE_TABLES_UPDATE, [ROUTEARG_DATABASE_TABLE_NAME => $this->tableName, ROUTEARG_PRIMARY_KEY => $primaryKeyValue]);

        return '<a href="'.$updatePath.'" title="update">'.$primaryKeyValue.'</a>';
    }

    /** constructs row with update and delete based on crud settings */
    private function getListViewRow(array $record): array 
    {
        if (!$this->mapper->getPrimaryKeyColumnName())
            return $record;
        // swap out primary key field value for update value
        $primaryKeyValue = $record[$this->mapper->getPrimaryKeyColumnName()];
        
        if ($this->updatesAuthorized) {
            $record[$this->mapper->getPrimaryKeyColumnName()] = $this->getUpdateCellValue($primaryKeyValue);
        }

        if ($this->deletesAuthorized) {
            $record[self::DELETE_COLUMN_TEXT] = $this->getDeleteCellValue($primaryKeyValue);
        }

        return $record;
    }

    protected function getErrorMessage(): ?string 
    {
        return $this->errorMessage;
    }

    // returns array of table content
    protected function getListArray(): array
    {
        $list = [];

        try {
            // added to account for tables without primary key
            $orderBy = ($this->orderByColumn) ? $this->orderByColumn . " " . self::ORDER_BY_DIRECTION : null;
            // if recordset is empty, list will be returned empty
            if ($recordSet = $this->mapper->select('*', $this->getFilterColumnsInfo(), $orderBy, $this->limitSelectRows)) {
                foreach ($recordSet as $record) {
                    $list[] = $this->getListViewRow($record);
                }
            }
        } catch (QueryFailureException $e) {
            $this->errorMessage = $e->getMessage();
        }

        return $list;
    }
}
