<?php
declare(strict_types=1);

namespace Exceptions;

// for an attempt to perform a query or transaction the model doesn't allow
class UnallowedActionException extends \RuntimeException 
{
    
}
