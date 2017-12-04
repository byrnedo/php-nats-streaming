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


    public function testUnixNanos()
    {
        $timeAsNanos = \NatsStreaming\Helpers\TimeHelpers::unixTimeNanos();

        sleep(1);

        $timeAsNanosAfter = \NatsStreaming\Helpers\TimeHelpers::unixTimeNanos();

        $this->assertInternalType('int', $timeAsNanos);

        $delta = $timeAsNanosAfter - $timeAsNanos;
        $this->assertGreaterThanOrEqual(1000000000, $delta);
        // margin of 10 microseconds
        $this->assertLessThan(1001000000, $delta);

        $timeNowNanos = \NatsStreaming\Helpers\TimeHelpers::unixTimeNanos();
        $timeNowSeconds = time();
        $this->assertEquals($timeNowSeconds, (int)($timeNowNanos / 1000000000));
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
        $r = $this->c->publish('foo', 'bar');
        $ack = $r->wait();
        $this->assertTrue($ack);
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


        // we'll only check the ack for this after all the others
        $latePubAckScenario = $this->c->publish($subject, 'foobar');
        $rs = [];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $this->c->publish($subject, 'foobar' . $i);
        }

        foreach ($rs as $r) {
            $gotAck = $r->wait();
            $this->assertTrue($gotAck);
        }

        // one more publish, check that this get's cached


        $got = 0;
        $sub = $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got + 1, $message->getSequence());
            $got ++;
        }, $subOptions);


        $sub->wait($toSend + 1);


        $ack = $latePubAckScenario->wait();
        $this->assertTrue($ack);

        $this->assertEquals($toSend + 1, $got);

        $this->c->close();
    }

    public function testGlobalWait()
    {

        $this->c->reconnect();

        $toSend = 100;

        $subject = 'test.globalwait.'.uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setStartAt(StartPosition::First());


        $rs = [];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $this->c->publish($subject, 'foobar' . $i);
        }

        foreach ($rs as $r) {
            $gotAck = $r->wait();
            $this->assertTrue($gotAck);
        }


        $got1 = 0;
        $sub = $this->c->subscribe($subject, function ($message) use (&$got1) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got1 + 1, $message->getSequence());
            $got1 ++;
        }, $subOptions);

        $got2 = 0;
        $sub2 = $this->c->subscribe($subject, function ($message) use (&$got2) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got2 + 1, $message->getSequence());
            $got2 ++;
        }, $subOptions);


        $this->c->natsCon()->setStreamTimeout(3);
        $this->c->wait();

        $this->assertEquals($toSend, $got1);
        $this->assertEquals($toSend, $got2);

        $this->c->close();
    }

    public function testMultipleSubscriptions()
    {

        $this->c->reconnect();

        $toSend = 100;

        $subject = 'test.subscribe.'.uniqid();

        $subOptions = new \NatsStreaming\SubscriptionOptions();


        $got1 = 0;
        $sub1 = $this->c->subscribe($subject, function ($message) use (&$got1) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got1 + 1, $message->getSequence());
            $got1 ++;
        }, $subOptions);

        $got2 = 0;
        $sub2 = $this->c->subscribe($subject, function ($message) use (&$got2) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got2 + 1, $message->getSequence());
            $got2 ++;
        }, $subOptions);

        $rs =[];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $this->c->publish($subject, 'foobar' . $i);
        }

        foreach ($rs as $r) {
            $gotAck = $r->wait(1);
            $this->assertTrue($gotAck);
        }


        $sub1->wait($toSend);
        $sub2->wait($toSend);

        $this->assertEquals($toSend, $got1);
        $this->assertEquals($toSend, $got2);

        $this->c->close();
    }


    /**
     * Test durable sub. Should pick up where it left off in case of a $sub->close or a $c->close
     */
    public function testDurableSubscription()
    {
        $this->c->reconnect();

        $toSend = 100;

        $subject = 'test.subscribe.durable.'.uniqid();

        $durable = 'durable';

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);
        //$subOptions->setStartAt(StartPosition::First());


        $got = 0;

        $sub = $this->c->subscribe($subject, function ($message) use (&$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got + 1, $message->getSequence());
            $got ++;
        }, $subOptions);

        $rs = [];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $this->c->publish($subject, 'foobar' . $i);
        }

        foreach ($rs as $r) {
            $gotAck = $r->wait();
            $this->assertTrue($gotAck);
        }

        $sub->wait($toSend);

        $this->assertEquals($toSend, $got);

        $this->c->close();

        $this->c->connect();
        $r = $this->c->publish($subject, 'foobarnew');
        $gotAck = $r->wait();
        $this->assertTrue($gotAck);

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);
        // should ignore last received option
        $subOptions->setStartAt(StartPosition::LastReceived());

        $got = 0;
        $sub = $this->c->subscribe($subject, function ($message) use (&$toSend, &$got) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($toSend + 1, $message->getSequence());
            $got ++;
        }, $subOptions);


        $sub->wait(1);

        $this->assertEquals(1, $got);

        $this->c->close();
    }

    /**
     */
    public function testMultipleDurableSubscription()
    {
        $this->c->reconnect();

        $toSend = 100;

        $subject = 'test.subscribe.durable.'.uniqid();

        $durable = 'durable';

        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);
        //$subOptions->setStartAt(StartPosition::First());

        $opts = new ConnectionOptions();
        $opts->setClientID($this->c->options->getClientID());
        $c2 = new Connection($opts);
        $c2->connect();


        $got1 = 0;

        $sub1 = $this->c->subscribe($subject, function ($message) use (&$got1) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got1 + 1, $message->getSequence());
            $got1 ++;
        }, $subOptions);

        $got2 = 0;

        $subOptions->setDurableName($durable.'-b');
        $sub2 = $c2->subscribe($subject, function ($message) use (&$got2) {
            /**
             * @var $message MsgProto
             */
            $this->assertEquals($got2 + 1, $message->getSequence());
            $got2 ++;
        }, $subOptions);


        $rs = [];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $this->c->publish($subject, 'foobar' . $i);
        }

        // quicker
        foreach ($rs as $r) {
            $gotAck = $r->wait();
            $this->assertTrue($gotAck);
        }

        $sub1->wait($toSend);
        $sub2->wait($toSend);

        $this->assertEquals($toSend, $got1);

        $c2->close();
        $c2->connect();
        $this->c->close();
        $this->c->connect();

        $r = $this->c->publish($subject, 'foobarnew');
        $gotAck = $r->wait();
        $this->assertTrue($gotAck);


        $subOptions = new \NatsStreaming\SubscriptionOptions();

        $subOptions->setDurableName($durable);
        $subOptions->setStartAt(StartPosition::LastReceived());

        $got1 = 0;
        $sub1 = $this->c->subscribe($subject, function ($message) use (&$toSend, &$got1) {
            /**
             * @var $message MsgProto
             */
            $got1 ++;
            $this->assertEquals($toSend + 1, $message->getSequence());
        }, $subOptions);

        $got2 = 0;
        $subOptions->setDurableName($durable.'-b');
        $sub2 = $this->c->subscribe($subject, function ($message) use (&$toSend, &$got2) {
            /**
             * @var $message MsgProto
             */
            $got2 ++;
            $this->assertEquals($toSend + 1, $message->getSequence());
        }, $subOptions);


        $sub1->wait(1);
        $sub2->wait(1);

        $this->assertEquals(1, $got1);
        $this->assertEquals(1, $got2);

        $this->c->close();
        try {
            $c2->close();
        } catch (\NatsStreaming\Exceptions\DisconnectException $e) {
            $this->assertEquals('stan: unknown clientID', $e->getMessage());
        }
    }

    /**
     * Test Queue Group Subscriptions
     */
    public function testQueueGroupSubscribe()
    {

        $subject = 'test.subscribe.qgroup.' . uniqid();
        $group = 'testQueueGroup';
        $toSend = 100;

        $c1 = $this->c;

        $options = new ConnectionOptions();
        $options->setClientID("test-2");
        $options->setClusterID("test-cluster");
        $c2 = new Connection($options);

        $c1->reconnect();
        $c2->connect();

        $subOptions = new \NatsStreaming\SubscriptionOptions();


        $got1 = 0;
        $allSeqs = [];
        $sub1 = $c1->queueSubscribe($subject, $group, function ($message) use (&$got1, &$allSeqs) {
            /**
             * @var $message MsgProto
             */
            $allSeqs["client1"][] = $message->getSequence();
            $got1 ++;
        }, $subOptions);

        $got2 = 0;
        $sub2 = $c2->queueSubscribe($subject, $group, function ($message) use (&$got2, &$allSeqs) {
            /**
             * @var $message MsgProto
             */
            $allSeqs["client2"][] = $message->getSequence();
            $got2 ++;
        }, $subOptions);

        $rs = [];
        for ($i = 0; $i < $toSend; $i++) {
            $rs[] = $c1->publish($subject, 'foobar' . $i);
        }

        foreach ($rs as $r) {
            $gotAck = $r->wait();
            $this->assertTrue($gotAck);
        }

        $c1->natsCon()->setStreamTimeout(1);
        $c2->natsCon()->setStreamTimeout(1);
        $c1->wait();
        $c2->wait();


        $client1Seqs = $allSeqs["client1"];
        $client2Seqs = $allSeqs["client2"];

        $totSeqs = array_merge($client1Seqs, $client2Seqs);

        $totSeqsUnique = array_unique($totSeqs);


        $this->assertEquals($toSend, $got1 + $got2);

        $this->assertEquals($toSend, count($totSeqsUnique));

        $c1->close();
        $c2->close();
    }

    /**
     * Test unsubscribing from channel
     * @param bool $close
     */
    public function testUnsubscribe($close = false)
    {

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

        $r = $this->c->publish($subject, 'foobar');
        $gotAck = $r->wait();
        $this->assertTrue($gotAck);

        $sub->wait(1);

        $this->assertEquals(1, $got);

        if ($close) {
            $sub->close();
        } else {
            $sub->unsubscribe();
        }

        $this->c->natsCon->setStreamTimeout(5);
        $r = $this->c->publish($subject, 'foobar');
        $gotAck = $r->wait();
        $this->assertTrue($gotAck);


        $this->assertEquals(1, $got);

        $this->c->close();
    }

    public function testSubscriptionClose()
    {

        $this->testUnsubscribe(true);
    }
}
