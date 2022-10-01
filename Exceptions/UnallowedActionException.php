<?php
declare(strict_types=1);

namespace Exceptions;

// for an attempt to perform a query or transaction the model doesn't allow
class UnallowedActionException extends \RuntimeException 
{
    const DEFAULT_MESSAGE = "Unallowed Model Action";

    public function __construct($message = null, $code = 0, \Exception $previous = null) {
        if (!$message) {
            $message = self::DEFAULT_MESSAGE;
        }
        parent::__construct($message, $code, $previous);
    }
}
