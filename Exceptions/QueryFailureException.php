<?php
declare(strict_types=1);

namespace Exceptions;

class QueryFailureException extends \RuntimeException 
{
    const DEFAULT_MESSAGE = "Query Failure";

    public function __construct($message = null, $code = 0, \Exception $previous = null) {
        if (!$message) {
            $message = self::DEFAULT_MESSAGE;
        }
        parent::__construct($message, $code, $previous);
    }
}
