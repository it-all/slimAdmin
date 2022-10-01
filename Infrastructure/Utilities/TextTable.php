<?php
declare(strict_types=1);

namespace Infrastructure\Utilities;

/**
 *  Helps print a table to the console.  Each field is padded so the columns line up nice.  For example:
 *
 *      Foo        123     stuff
 *      Foofoofoo  45      more stuff here
 *      Barbar     123456  the rest
 *
 *  Example use:
 *
 *      $t = new TextTable();
 *      $t->addRow("Foo", 123, "stuff");
 *      $t->addRow("Foofoofoo", 45, "more stuff here");
 *      $t->addRow("Barbar", 123456, "the rest");
 *      echo $t->toString();
 *
 *  That would produce the output above.
 *
 */
class TextTable 
{

    private $rows = [];
    private $widths = [];

    public $cellPadding = "  ";

    public function addRow(...$arr) 
    {
        // $arr = func_get_args();
        $this->rows[] = $arr;
        for ($i=0; $i<count($arr); $i++) {
            $field = $arr[$i];
            $field = "$field";
            $w = $this->widths[$i] ?? 0;
            $this->widths[$i] = max($w, strlen($field));
        }
    }

    public function toString(): string 
    {
        $str = '';
        $lastRow = count($this->rows) - 1;
        foreach ($this->rows as $ri => $row) {
            $lastCell = count($row) - 1;
            foreach ($row as $i => $val) {
                $w = $this->widths[$i];
                $padded = str_pad($val, $w);
                $str .= $padded;
                if ($i != $lastCell) {
                    $str .= $this->cellPadding;
                }
            }
            if ($ri != $lastRow) {
                $str .= "\n";
            }
        }
        return $str;
    }
}
