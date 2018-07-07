<?php
declare(strict_types=1);

namespace SlimPostgres\Exceptions;

// for an attempt to perform a query the model doesn't allow
class UnallowedQueryException extends \RuntimeException 
{
    
}
