<?php

namespace NatsStreaming\Exceptions;

use Exception;
use Throwable;

class UnsubscribeException extends Exception
{

    /**
     * UnsubscribeException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "error while unsubscribing", $code = 0, Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
