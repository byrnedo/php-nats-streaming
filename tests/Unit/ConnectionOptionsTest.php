<?php

class ConnectionOptionsTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {

        $opts = new \NatsStreaming\ConnectionOptions([
            'discoverPrefix' => 'foo',
            'clientID' => 'bar',
            'clusterID' =>'baz',
            'foobar' => 'baz',
            'natsOptions' => new \Nats\ConnectionOptions(['host' => 'hosty'])
        ]);

        $this->assertEquals('foo', $opts->getDiscoverPrefix());
        $this->assertEquals('bar', $opts->getClientID());
        $this->assertEquals('baz', $opts->getClusterID());

        $this->assertEquals('hosty', $opts->getNatsOptions()->getHost());
    }
}
