<?php

class SubscriptionOptionsTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {

        $opts = new \NatsStreaming\SubscriptionOptions([
            'durableName' => 'foo',
            'maxInFlight' => 2,
            'ackWaitSecs' => 99,
            'startAt' => \NatsStreamingProtos\StartPosition::SequenceStart(),
            'startSequence' => 100,
            'startMicroTime' => 1000,
            'manualAck' => true,
        ]);

        $this->assertEquals('foo', $opts->getDurableName());
        $this->assertEquals(2, $opts->getMaxInFlight());
        $this->assertEquals(99, $opts->getAckWaitSecs());
        $this->assertEquals(\NatsStreamingProtos\StartPosition::SequenceStart()->value(), $opts->getStartAt()->value());
        $this->assertEquals(100, $opts->getStartSequence());
        $this->assertEquals(true, $opts->isManualAck());
    }
}
