<?php

namespace NatsStreaming\Exceptions;

use Exception;
use Throwable;

class DisconnectException extends Exception
{

    /**
     * DisconnectException constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "error while disconnecting", $code = 0, Throwable $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}
