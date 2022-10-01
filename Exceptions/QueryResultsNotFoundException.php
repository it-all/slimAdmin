<?php
declare(strict_types=1);

namespace Exceptions;

class QueryResultsNotFoundException extends \RuntimeException 
{
    const DEFAULT_MESSAGE = "Query Results Not Found";

    public function __construct($message = null, $code = 0, \Exception $previous = null) {
        if (!$message) {
            $message = self::DEFAULT_MESSAGE;
        }
        parent::__construct($message, $code, $previous);
    }
}
