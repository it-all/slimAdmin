<?php
declare(strict_types=1);

namespace Infrastructure\Database\Queries;

class UpdateBuilder extends InsertUpdateBuilder {
    public $updateOnColumnName;
    public $updateOnColumnValue;
    public $setColumnsValues;

    function __construct(string $dbTable, string $updateOnColumnName, $updateOnColumnValue)
    {
        $this->updateOnColumnName = $updateOnColumnName;
        $this->updateOnColumnValue = $updateOnColumnValue;
        parent::__construct($dbTable);
    }

    /**
     * adds column to update query
     * @param string $name
     * @param $value
     */
    public function addColumn(string $name, $value)
    {
        $this->args[] = $value;
        if (count($this->args) > 1) {
            $this->setColumnsValues .= ", ";
        }
        $argNum = count($this->args);
        $this->setColumnsValues .= "$name = \$".$argNum;
    }

    /**
     * @param array $updateColumns
     */
    public function addColumnsArray(array $updateColumns)
    {
        foreach ($updateColumns as $name => $value) {
            $this->addColumn($name, $value);
        }
    }

    /**
     * sets update query
     */
    public function setSql()
    {
        $this->args[] = $this->updateOnColumnValue;
        $lastArgNum = count($this->args);
        $this->sql = "UPDATE $this->dbTable SET $this->setColumnsValues WHERE $this->updateOnColumnName = $".$lastArgNum;
    }
}
