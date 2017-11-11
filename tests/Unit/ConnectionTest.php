<?php

use NatsStreaming\Connection;
use NatsStreaming\ConnectionOptions;
use NatsStreamingProtos\MsgProto;
use NatsStreamingProtos\StartPosition;

/**
 * Class ConnectionTest.
 */
class ConnectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Client.
     *
     * @var Connection Client
     */
    private $c;
    /**
     * SetUp test suite.
     *
     * @return void
     */
    public function setUp()
    {
        $options = new ConnectionOptions();
        $options->setClientID("test");
        $options->setClusterID("test-cluster");
        $this->c = new Connection($options);
        //$this->c->connect();
    }
    /**
     * Test Connection.
     *
     * @return void
     */
    public function testConnection()
    {
        // Connect.
        $this->c->connect();
        $this->assertTrue($this->c->isConnected());
        // Disconnect.
        $this->c->close();
        $this->assertFalse($this->c->isConnected());
    }

    /**
     * Test Publish command.
     *
     * @return void
     */
    public function testPublish()
    {
        $this->c->connect();
        $this->c->publish('foo', 'bar');
        $count = $this->c->pubsCount();
        $this->assertInternalType('int', $count);
        $this->assertGreaterThan(0, $count);
        $this->c->close();
    }

    /**
     * Test Reconnect command.
     *
     * @return void
     */
    public function testReconnect()
    {
        $this->c->connect();
        $this->assertTrue($this->c->isConnected());
        $this->c->reconnect();
        $count = $this->c->reconnectsCount();
        $this->assertInternalType('int', $count);
        $this->assertGreaterThan(0, $count);
        $this->c->close();
    }

    /**
     * Test Subscribe Command
     */
    public function testSubscribe()
    {
        $this->c->connect();

        $subject = 'test.subscribe.'.uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setStartAt(StartPosition::First());

        for ($i = 0; $i < 10; $i++) {
            $this->c->publish($subject, 'foobar' . $i);
        }


        $got = 0;
        $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals('foobar' . $got, $message->getData());
            $got ++;
        }, $subOptions);

        $this->c->wait(10);

        $this->assertEquals(10, $got);

        $this->c->close();
    }

    /**
     * Test Queue Group Subscriptions
     */
    public function testQueueGroupSubscribe(){

        $this->c->connect();

        $subject = 'test.subscribe.qgroup';

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $got = 0;
        $this->c->queueSubscribe($subject, 'testQueueGroup', function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals('foobar' . $got, $message->getData());
            $got ++;
        }, $subOptions);

        for ($i = 0; $i < 10; $i++) {
            $this->c->publish($subject, 'foobar' . $i);
        }

        $this->c->wait(10);

        $this->assertEquals(10, $got);

        $this->c->close();
    }

    /**
     * Test unsubscribing from channel
     */
    public function testUnsubscribe(){

        $this->c->connect();

        $subject = 'test.unsub';

        $subOptions = new \NatsStreaming\SubscriptionOptions();


        $sub = $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals('foobar', $message->getData());
        }, $subOptions);

        $this->c->publish($subject, 'foobar' );

        $this->c->wait(1);

        $sub->unsubscribe();

        $this->c->publish($subject, 'foobar' );

        $this->c->natsConn()->setStreamTimeout(1);

        $this->c->wait(1);

    }
}
