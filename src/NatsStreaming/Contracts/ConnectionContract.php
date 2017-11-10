<?php

namespace NatsStreaming\Contracts;

interface ConnectionContract
{


    public function publish($subject, $data);

    //public function publishAsync($subject, $data, callable $ackHander);

//    public function subscribe($subjects, callable $cb, $subscriptionOptions);
//
//    public function queueSubscribe($subjects, $qGroup, callable $cb, $subscriptionOptions);

    public function connect();
    public function close();

    // NatsConn returns the underlying NATS conn. Use this with care. For
    // example, closing the wrapped NATS conn will put the NATS Streaming Conn
    // in an invalid state.
    public function natsConn();
}
