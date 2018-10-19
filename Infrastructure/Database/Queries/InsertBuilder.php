<?php
declare(strict_types=1);

namespace Infrastructure\Database\Queries;

class InsertBuilder extends InsertUpdateBuilder
{
    /** @var string */
    protected $columns = '';

    /** @var string */
    protected $values = '';

    /**
     * adds column to insert query
     * @param string $name
     * @param $value
    */
    public function addColumn(string $name, $value)
    {
        $this->args[] = $value;
        if (mb_strlen($this->columns) > 0) {
            $this->columns .= ", ";
        }
        $this->columns .= $name;
        if (mb_strlen($this->values) > 0) {
            $this->values .= ", ";
        }
        $argNum = count($this->args);
        $this->values .= "$".$argNum;
    }

    /**
     * sets insert query
     */
    public function setSql()
    {
        $this->sql = "INSERT INTO $this->dbTable ($this->columns) VALUES($this->values)";
    }
}
