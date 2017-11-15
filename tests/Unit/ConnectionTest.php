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
        $this->c->close();
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
        $this->c->reconnect();
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
        $this->c->close();
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
        $this->c->reconnect();


        $toSend = 100;

        $subject = 'test.subscribe.'.uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setStartAt(StartPosition::First());

        for ($i = 0; $i < $toSend; $i++) {
            $this->c->publish($subject, 'foobar' . $i);
        }


        $got = 0;
        $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got + 1, $message->getSequence());
            $got ++;
        }, $subOptions);

        $this->c->wait($toSend);

        $this->assertEquals($toSend, $got);

        $this->c->close();
    }

    /**
     * Test durable sub. Should pick up where it left off in case of a $sub->close or a $c->close
     */
    public function testDurableSubscription(){
        $this->c->reconnect();

        $toSend = 100;

        $subject = 'test.subscribe.durable.'.uniqid();

        $durable = 'durable';

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);

        $got = 0;
        $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got + 1, $message->getSequence());
            $got ++;
        }, $subOptions);

        for ($i = 0; $i < $toSend; $i++) {
            $this->c->publish($subject, 'foobar' . $i);
        }

        $this->c->wait($toSend);

        $this->assertEquals($toSend, $got);

        $this->c->close();

        $this->c->connect();
        $this->c->publish($subject, 'foobarnew');

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);

        $sub = $this->c->subscribe($subject, function ($message) use (&$toSend) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($toSend + 1, $message->getSequence());
        }, $subOptions);


        $this->c->wait(1);

        $this->c->close();

    }

    /**
     * Test Queue Group Subscriptions
     */
    public function testQueueGroupSubscribe(){

        $this->c->reconnect();

        $subject = 'test.subscribe.qgroup.' . uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $toSend = 100;

        $got = 0;
        $this->c->queueSubscribe($subject, 'testQueueGroup', function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got + 1, $message->getSequence());
            $got ++;
        }, $subOptions);

        for ($i = 0; $i < $toSend; $i++) {
            $this->c->publish($subject, 'foobar' . $i);
        }

        $this->c->wait($toSend);

        $this->assertEquals($toSend, $got);

        $this->c->close();
    }

    /**
     * Test unsubscribing from channel
     * @param bool $close
     */
    public function testUnsubscribe($close = false){

        $this->c->connect();

        $subject = 'test.unsub.'.uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();


        $got = 0;
        $sub = $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $got ++;
            $this->assertEquals('foobar', $message->getData());
        }, $subOptions);

        $this->c->publish($subject, 'foobar' );

        $this->c->wait(1);

        $this->assertEquals(1, $got);

        if ($close) {
            $sub->close();
        } else {
            $sub->unsubscribe();
        }

        $this->c->publish($subject, 'foobar' );

        $this->c->natsConn()->setStreamTimeout(1);

        $this->c->wait(1);

        $this->assertEquals(1, $got);

        $this->c->close();

    }

    public function testSubscriptionClose() {

        $this->testUnsubscribe(true);
    }
}
