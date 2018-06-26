<?php

declare(strict_types=1);

namespace SlimPostgres\Utilities;

// A place to store php helper functions, not App-related functions
class Functions
{
    static public function removeLastCharFromString(string $in): string 
    {
        return substr($in, 0, strlen($in) - 1);
    }

    static public function getIntOrNull(?string $input)
    {
        return ($input == null) ? null : (int) $input;
    }

    /**
     * converts array to string
     * @param array $arr
     * @param int $level
     * @return string
     */
    static public function arrayWalkToStringRecursive(array $arr, int $level = 0, int $maxLevel = 1000, $newLine = '<br>'): string
    {
        $out = "";
        $tabs = " ";
        for ($i = 0; $i < $level; $i++) {
            $tabs .= " ^"; // use ^ to denote another level
        }
        foreach ($arr as $k => $v) {
            $out .= "$newLine$tabs$k: ";
            if (is_object($v)) {
                $out .= 'object type: '.get_class($v);
            } elseif (is_array($v)) {
                $newLevel = $level + 1;
                if ($newLevel > $maxLevel) {
                    $out .= ' array too deep, quitting';
                } else {
                    $out .= self::arrayWalkToStringRecursive($v, $newLevel, $maxLevel, $newLine);
                }
            } else {
                $out .= (string)$v;
            }
        }
        return $out;
    }

    /**
     * Returns true if the current script is running from the command line (ie, CLI).
     */
    static public function isRunningFromCommandLine(): bool
    {
        return php_sapi_name() == 'cli';
    }
}
