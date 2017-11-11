<?php

namespace NatsStreaming\Contracts;

use NatsStreaming\SubscriptionOptions;
use NatsStreamingProtos\MsgProto;

interface ConnectionContract
{


    /**
     * @param $subject
     * @param $data
     * @return void
     */
    public function publish($subject, $data);

    //public function publishAsync($subject, $data, callable $ackHander);

    /**
     * @param $subject
     * @param callable $cb
     * @param SubscriptionOptions $subscriptionOptions
     * @return void
     */
    public function subscribe($subject, callable $cb, $subscriptionOptions);

    /**
     * @param $subject
     * @param $qGroup
     * @param callable $cb
     * @param SubscriptionOptions $subscriptionOptions
     * @return mixed
     */
    public function queueSubscribe($subject, $qGroup, callable $cb, $subscriptionOptions);

    public function wait($quantity = 0);

    public function connect();
    public function close();

    // NatsConn returns the underlying NATS conn. Use this with care. For
    // example, closing the wrapped NATS conn will put the NATS Streaming Conn
    // in an invalid state.
    public function natsConn();
}
