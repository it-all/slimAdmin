<?php

declare(strict_types=1);

namespace Infrastructure;

// A place to store php helper functions, not App-related functions
class Functions
{
    public static function validateJson(string $json): bool 
    {
        return json_decode($json) !== null;
    }
    
    /** string length must be >= numChars */
    public static function removeLastCharsFromString(string $input, int $numChars = 1): string 
    {
        if ($numChars > strlen($input)) {
            throw new \InvalidArgumentException("Cannot remove $numChars from $input");
        }
        return substr($input, 0, strlen($input) - $numChars);
    }

    /**
     * converts array to string
     * @param array $arr
     * @param int $level
     * @return string
     */
    public static function arrayWalkToStringRecursive(array $arr, int $level = 0, int $maxLevel = 1000, $newLine = '<br>'): string
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
    public static function isRunningFromCommandLine(): bool
    {
        return php_sapi_name() == 'cli';
    }

    public static function getFirstName(string $fullName): string
    {
        // Strip mr, mrs, dr, ms sometimes with '.', sometimes with space
        $without_title = preg_replace('/^(mr|mrs|dr|ms)(\.| )\s*/i', '', $fullName);
        $parts = preg_split('( |\.)', $without_title);
        $first = ucfirst(strtolower($parts[0]));
        return $first;
    }

    public static function getLastName(string $fullName): string
    {
        // Strip mr, mrs, dr, ms sometimes with '.', sometimes with space
        $without_title = preg_replace('/^(mr|mrs|dr|ms)(\.| )\s*/i', '', $fullName);
        $parts = preg_split('( |\.)', $without_title);
        $last = count($parts) < 2 ? '' : ucfirst(strtolower($parts[count($parts) - 1]));
        return $last;
    }

    public static function getInformalDate(string $dbDate, bool $returnYear=true)
    {
        if (!self::isDbDate($dbDate)) {
            throw new \Exception("Invalid input date");
        }
        $dstring = ($returnYear) ? "n/j/y" : "n/j";
        $d = date($dstring, mktime((int) date("H"), (int) date("i"), (int) date("s"), (int) substr($dbDate, 5, 2), (int) substr($dbDate, 8, 2), (int) substr($dbDate, 0, 4)));
        return($d);
    }

    // VALIDATION FUNCTIONS

    /**
     * Check in for being an integer
     * either type int or the string equivalent of an integer
     * @param $in any type
     * note empty string returns false
     * note 0 or "0" returns true (as it should - no 0 problem as is mentioned by some sites)
     * note 4.00 returns true but "4.00" returns false
     * @return bool
     */
    public static function isInteger($check): bool 
    {
        return (filter_var($check, FILTER_VALIDATE_INT) === false) ? false : true;
    }

    public static function isWholeNumber($check): bool 
    {
        return (!self::isInteger($check) || $check < 0) ? false : true;
    }

    /**
     * checks if string is blank or null
     * this can be helpful for validating required form fields
     * @param string $check
     * @return bool
     */
    public static function isBlankOrNull($check, $trim=true): bool 
    {
        if($trim) {
            $check = trim($check);
        }
        return (strlen($check) == 0 || is_null($check));
    }

    /**
     * checks if string is blank or zero
     * this can be helpful for validating numeric/integer form fields
     * @param string $check
     * @return bool
     */
    public static function isBlankOrZero($check, $trim=true): bool 
    {
        if($trim) {
            $check = trim($check);
        }
        return (strlen($check) == 0 || $check == 0);
    }

    /**
     * checks if string is a positive integer
     * @param string $check
     * @return bool
     */
    public static function isPositiveInteger($check): bool 
    {
        return (self::isInteger($check) && $check > 0);
    }


    public static function isNumericPositive($check): bool 
    {
        if (!is_numeric($check) || $check <= 0) {
            return false;
        }
        return true;
    }

    /**
     * @param string $check
     * @return bool
     * format YYYY-mm-dd
     */
    public static function isDbDate($check): bool 
    {
        // todo use regex
        if (strlen($check) != 10) {
            return false;
        }
        if(substr($check, 4, 1) != "-" || substr($check, 7, 1) != "-" ) {
            return false;
        }
        // if all zeros not ok
        if ($check == '0000-00-00') {
            return false;
        }
        $yr = substr($check, 0, 4);
        $mo = substr($check, 5, 2);
        $dy = substr($check, 8, 2);
        // date either in 1900s or 2000s
        if (substr($yr, 0, 2) != '19' && substr($yr, 0, 2) != '20') {
            return false;
        }
        if ($mo > 12 || !is_numeric($mo) || (substr($mo, 0, 1) != '0' && substr($mo, 0, 1) != '1') ) {
            return false;
        }
        if($dy > 31 || !is_numeric($dy) || (substr($mo, 0, 1) != '0' && substr($mo, 0, 1) != '1') ) {
            return false;
        }
        return true;
    }

    /**
     * @param $dbDate has already been verified to be isDbDate()
     * @return bool
     */
    public static function isDbDateInPast($dbDate):bool 
    {
        return self::dbDateCompare($dbDate) < 0;
    }

    /**
     * @param $d1
     * @param $d2 if null compare to today
     * d1, d2 already verified to be isDbDate()
     * @return int
     */
    public static function dbDateCompare ($d1, $d2 = null): int 
    {
        // inputs 2 mysql dates and returns d1 - d2 in seconds
        if ($d2 === null) {
            $d2 = date('Y-m-d');
        }
        return self::convertDateMktime($d1) - self::convertDateMktime($d2);
    }

    /**
     * @param $dbDate already been verified to be isDbDate()
     * @return int
     */
    public static function convertDateMktime($dbDate): int 
    {
        return mktime(0, 0, 0, (int) substr($dbDate, 5, 2), (int) substr($dbDate, 8, 2), (int) substr($dbDate, 0, 4));
    }

    public static function isDigit($check): bool
    {
        if (strlen($check) != 1 || !self::isInteger($check)) {
            return false;
        }
        return true;
    }

    public static function isTwoCharNumber($check, $max = 99, $leadingZeroOk = true): bool 
    {
        if (strlen($check) != 2) {
            return false;
        }
        if (!self::isDigit(substr($check, 0, 1)) || !self::isDigit(substr($check, 1))) {
            return false;
        }
        if (!$leadingZeroOk && substr($check, 0, 1) == '0') {
            return false;
        }
        $checkInt = (int) $check;
        if ($checkInt > $max) {
            return false;
        }
        return true;
    }

    public static function isDbMilitaryHours($check): bool 
    {
        // 00 - 23
        return self::isTwoCharNumber($check, 23);
    }

    public static function isMinutes($check): bool 
    {
        // 00 - 59
        return self::isTwoCharNumber($check, 59);
    }

    public static function isSeconds($check): bool 
    {
        // 00 - 59
        return self::isMinutes($check);
    }

    public static function isDbTimestamp($check): bool 
    {
        // todo use regex
        if (!self::isDbDate(substr($check, 0, 10))) {
            return false;
        }
        // remainder of string like  10:08:16.717238
        if (substr($check, 10, 1) != ' ') {
            return false;
        }
        $timeParts = explode(":", substr($check, 11));
        // ok without seconds
        if (count($timeParts) != 2 && count($timeParts) != 3) {
            return false;
        }
        foreach ($timeParts as $index => $timePart) {
            if ($index == 0) {
                if (!self::isDbMilitaryHours($timePart)) {
                    return false;
                }
            }
            elseif ($index == 1) {
                if (!self::isMinutes($timePart)) {
                    return false;
                }
            }
            else {
                if (!self::isSeconds(substr($timePart, 0, 2))) {
                    return false;
                }
                if (strlen($timePart) > 2 && !self::is_numeric(substr($timePart, 2))) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function isEmail($check) 
    {
        return filter_var($check, FILTER_VALIDATE_EMAIL);
    }


    // END VALIDATION FUNCTIONS

}
