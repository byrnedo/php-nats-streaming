<?php
namespace NatsStreaming;

/**
 * Class Exception
 *
 * @package Nats
 */
class Exception extends \Exception
{


    /**
     * Creates an Exception for a failed connection.
     *
     * @param string $response The failed error response.
     *
     * @return Exception
     */
    public static function forFailedConnection($response)
    {
        return new static(sprintf('Failed to connect: %s', $response));
    }

    /**
     * Creates an Exception for a failed ack response.
     *
     * @param string $response The failed error response.
     *
     * @return Exception
     */
    public static function forFailedAck($response)
    {
        return new static(sprintf('Error in Ack message: %s', $response));
    }

}
