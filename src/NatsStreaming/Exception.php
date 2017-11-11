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

    /**
     * Creates an Exception for a failed disconnection.
     *
     * @param string $response The failed error response.
     *
     * @return Exception
     */
    public static function forFailedDisconnection($response)
    {
        return new static(sprintf('Failed to disconnect: %s', $response));
    }

    public static function forFailedSubscription($response)
    {
        return new static(sprintf('Failed to subscribe: %s', $response));
    }

    public static function forFailedUnsubscribe($response)
    {
        return new static(sprintf('Failed to unsubscribe: %s', $response));
    }


    public static function forTimeout($response)
    {

        return new static(sprintf('Possible timeout: %s', $response));
    }
}
