<?php

namespace IDT\Core\PrintApi;

use Exception;

/**
 * Generic exception thrown by the Print API client.
 */
class PrintApiException extends Exception
{
    public function __construct($message = null, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
