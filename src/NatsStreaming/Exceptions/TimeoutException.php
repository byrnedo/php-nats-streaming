<?php

namespace NatsStreaming\Exceptions;

use Exception;
use Throwable;

class TimeoutException extends Exception
{

    /**
     * TimeoutException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "timeout occured waiting for response", $code = 0, Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
