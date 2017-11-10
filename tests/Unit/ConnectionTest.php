<?php

use NatsStreaming\Connection;
use NatsStreaming\ConnectionOptions;

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

    public function testSubscribe(){
        $this->c->connect();

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $got = 0;
        $this->c->subscribe('test.subscribe', function($message) use (&$got){

            $got ++;
        }, $subOptions);



        for ($i = 0; $i < 10; $i++) {

            $this->c->publish('test.subscribe', 'foobar');
        }


        $this->c->wait(10);

        $this->assertEquals(10, $got);
    }
}
