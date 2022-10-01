<?php
declare(strict_types=1);

namespace Exceptions;

class QueryUpdateNoChangesException extends \RuntimeException 
{
    const DEFAULT_MESSAGE = "Update Query With No Changes";

    public function __construct($message = null, $code = 0, \Exception $previous = null) {
        if (!$message) {
            $message = self::DEFAULT_MESSAGE;
        }
        parent::__construct($message, $code, $previous);
    }
}
